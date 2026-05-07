# Laravel SaaS: Event-Driven User Notifications

A Laravel application that demonstrates a classic SaaS notification pattern: domain events (teammate invited, task assigned, comment posted) dispatch through Laravel's event system and trigger transactional emails via [Mailtrap](https://mailtrap.io). A scheduled artisan command compiles a weekly activity digest for each team.

No real product features are built here — the app exposes simulator endpoints so you can exercise the full email integration end-to-end without standing up a whole SaaS.

## How It Works

```
HTTP request                       Event dispatched       Listeners (auto-discovered)
─────────────                      ───────────────        ──────────────────────────
POST /events/invite              → TeammateInvited    ─┬─→ SendTeammateInvitedEmail
                                                      └─→ RecordTeammateInvitedActivity

POST /events/task-assigned       → TaskAssigned       ─┬─→ SendTaskAssignedEmail
                                                      └─→ RecordTaskAssignedActivity

POST /events/comment             → CommentPosted      ─┬─→ SendCommentPostedEmail
                                                      └─→ RecordCommentPostedActivity

php artisan digest:send                               ──→ NotificationMailer (weekly_digest template)
  (also wired to Laravel's scheduler — runs every Monday at 09:00)
```

Each event has two independent listeners. One sends the Mailtrap email, the other records the event for the weekly digest. Failures are isolated — a missing template UUID or a Mailtrap outage in the "send email" listener does not prevent activity from being recorded, and the digest continues to the next team if one team's send fails.

## Features

- **Event-driven architecture** — Dispatch a Laravel event from anywhere in the app; listeners are auto-discovered from `app/Listeners` by Laravel 11's convention (no manual `Event::listen` calls needed)
- **Decoupled listeners** — Adding a new notification type means adding an Event class and a Listener class. No existing listener needs to change
- **Mailtrap templates** — One template UUID per notification type (configurable via env); template variables are populated from event data
- **Weekly digest** — Scheduled artisan command compiles recent activity per team and sends one digest email per team owner
- **Isolated failure handling** — `NotificationMailer::send()` returns `bool` and logs errors so a single bad send does not break the batch
- **Simulator endpoints** — HTTP endpoints that mirror real user actions (invite flow, task assignment, commenting) so the integration can be tested without a real product

## Prerequisites

- PHP 8.3+
- [Composer](https://getcomposer.org/)
- [Mailtrap account](https://mailtrap.io) with a verified sending domain and four email templates (see [Mailtrap Template Setup](#mailtrap-template-setup))

## Setup

1. **Clone and install**

```bash
git clone https://github.com/mailtrap/laravel-event-notifications.git
cd laravel-event-notifications
composer install
```

2. **Configure environment**

```bash
cp .env.example .env
php artisan key:generate
```

3. **Set your API keys and template UUIDs** in `.env`

```
MAILTRAP_API_KEY=your_mailtrap_api_key
MAIL_FROM_ADDRESS=no-reply@yourdomain.com

MAILTRAP_TEMPLATE_TEAMMATE_INVITED=your_teammate_invited_template_uuid
MAILTRAP_TEMPLATE_TASK_ASSIGNED=your_task_assigned_template_uuid
MAILTRAP_TEMPLATE_COMMENT_POSTED=your_comment_posted_template_uuid
MAILTRAP_TEMPLATE_WEEKLY_DIGEST=your_weekly_digest_template_uuid

DIGEST_WINDOW_DAYS=7
```

4. **Create the database and seed sample data**

```bash
touch database/database.sqlite
php artisan migrate --seed
```

This creates two sample teams (Acme Engineering and Globex Product) with five users, three tasks, and one seed comment.

5. **Run the app**

```bash
php artisan serve
```

## Mailtrap Template Setup

Create four Mailtrap templates — one per notification key. Each template has its own variables:

| Template env var | Variables available |
|---|---|
| `MAILTRAP_TEMPLATE_TEAMMATE_INVITED` | `invitee_name`, `inviter_name`, `team_name` |
| `MAILTRAP_TEMPLATE_TASK_ASSIGNED` | `assignee_name`, `assigner_name`, `task_title`, `task_description`, `task_id` |
| `MAILTRAP_TEMPLATE_COMMENT_POSTED` | `owner_name`, `author_name`, `task_title`, `task_id`, `comment_body` |
| `MAILTRAP_TEMPLATE_WEEKLY_DIGEST` | `owner_name`, `team_name`, `window_days`, `event_count`, `events[]` |

For each template:

1. Go to [Mailtrap](https://mailtrap.io) > **Sending** > **Email Templates** > **Add Template**
2. Design the HTML using the variables above
3. Copy the template UUID from the URL or template list
4. Paste it into the matching env var in `.env`

## Simulating Events

Use the HTTP endpoints to dispatch each event. The endpoints validate input, persist the necessary rows, and then dispatch the matching Laravel event, which triggers both the notification email and the activity recording listeners.

All `/api/*` routes return JSON regardless of the `Accept` header. Validation failures come back as HTTP 422 with field-level messages under `errors.<field>`.

### Invite a teammate

```bash
curl -X POST http://localhost:8000/api/events/invite \
  -H "Content-Type: application/json" \
  -d '{
    "inviter_email": "alice@acme.test",
    "invitee_name": "Diana Ward",
    "invitee_email": "diana@acme.test"
  }'
```

### Assign a task

```bash
curl -X POST http://localhost:8000/api/events/task-assigned \
  -H "Content-Type: application/json" \
  -d '{
    "task_id": 1,
    "assigner_email": "alice@acme.test",
    "assignee_email": "bob@acme.test"
  }'
```

### Post a comment

```bash
curl -X POST http://localhost:8000/api/events/comment \
  -H "Content-Type: application/json" \
  -d '{
    "task_id": 1,
    "author_email": "bob@acme.test",
    "body": "I pushed a fix for the performance issue."
  }'
```

> The comment listener skips notifying the task owner if the owner is also the comment author — no "you commented on your own task" spam.
> To see this in action, author a comment as alice@acme.test on task 1 — she is the task owner; the email send is skipped but the activity is still recorded.

## Weekly Digest

Trigger the digest manually at any time:

```bash
# Send digests for all teams, using the default 7-day window
php artisan digest:send

# Send only for a specific team
php artisan digest:send --team=1

# Override the window (e.g. for ad-hoc monthly digests)
php artisan digest:send --days=30
```

The digest is also wired to Laravel's scheduler in `bootstrap/app.php`:

```php
->withSchedule(function (Schedule $schedule) {
    $schedule->command('digest:send')->weeklyOn(1, '9:00');
})
```

To run scheduled tasks in production, set up the usual Laravel scheduler cron entry:

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

## Adding a New Notification Type

The event-driven architecture is designed so adding a new notification is a matter of adding two new files:

1. **Create an Event class** in `app/Events/`:

```php
namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ProjectArchived
{
    use Dispatchable;

    public function __construct(public readonly Project $project, public readonly User $actor) {}
}
```

2. **Create one or more Listener classes** in `app/Listeners/`. The `handle()` method's type hint determines which event it subscribes to:

```php
namespace App\Listeners;

use App\Events\ProjectArchived;
use App\Services\NotificationMailer;

class SendProjectArchivedEmail
{
    public function __construct(private readonly NotificationMailer $mailer) {}

    public function handle(ProjectArchived $event): void
    {
        $this->mailer->send(
            notificationKey: 'project_archived',
            recipientEmail: $event->project->owner->email,
            recipientName: $event->project->owner->name,
            variables: ['project_name' => $event->project->name],
        );
    }
}
```

3. **Register the notification in `config/notifications.php`** — add an entry like `'project_archived' => env('MAILTRAP_TEMPLATE_PROJECT_ARCHIVED')`, and set the env var in `.env`. Without this line, `NotificationMailer::send()` will log a warning and skip the send.

> If the new event should appear in the weekly digest, also add a case to `App\Console\Commands\SendWeeklyDigest::typeLabel()` so it renders with a friendly name.

That's it. Laravel 11 auto-discovers listeners in `app/Listeners/` by their `handle()` type hint — no manual registration needed. No existing listener changes.

If the new event is triggered from a new simulator endpoint, add a matching FormRequest in `app/Http/Requests/` and type-hint it on the controller action — this sample keeps all input validation, email normalization, and cross-model guards in those FormRequest classes.

## Project Structure

```
app/
  Console/Commands/
    SendWeeklyDigest.php              # php artisan digest:send
  Events/
    TeammateInvited.php
    TaskAssigned.php
    CommentPosted.php
  Http/Controllers/
    EventSimulatorController.php      # POST /events/{invite,task-assigned,comment} — persists + dispatches events
  Http/Requests/
    InviteTeammateRequest.php         # Validation + email normalization for /events/invite
    AssignTaskRequest.php             # Validation + cross-team guards for /events/task-assigned
    PostCommentRequest.php            # Validation + cross-team guard for /events/comment
  Listeners/
    Send{Event}Email.php              # Sends the Mailtrap notification
    Record{Event}Activity.php         # Records event for the weekly digest
  Models/
    Team.php, User.php, Task.php, Comment.php, ActivityEvent.php
  Services/
    NotificationMailer.php            # Mailtrap template send helper
    ActivityRecorder.php              # Activity event persistence helper
bootstrap/
  app.php                             # Registers scheduler + api/console routing
config/
  notifications.php                   # Template UUIDs + digest window
  services.php                        # Mailtrap API key
database/
  migrations/                         # teams, users, tasks, comments, activity_events
  seeders/DatabaseSeeder.php          # Two sample teams with members and tasks
routes/
  api.php                             # Simulator endpoints
  console.php                         # (Custom artisan routes, empty by default)
tests/Feature/
  EventSimulatorTest.php              # Endpoint validation + event dispatching
  WeeklyDigestTest.php                # Digest command scenarios
  NotificationMailerTest.php          # Mailer config + error paths
```

## Running Tests

```bash
./vendor/bin/phpunit
```

Tests cover endpoint validation, event dispatching, listener side effects, graceful mail failure, and digest compilation — all without requiring real Mailtrap credentials.

## Links

- [Mailtrap Email API docs](https://mailtrap.io/email-api)
- [Mailtrap PHP SDK](https://github.com/railsware/mailtrap-php)
- [Laravel Events](https://laravel.com/docs/events)
- [Laravel Scheduling](https://laravel.com/docs/scheduling)

## License

MIT
