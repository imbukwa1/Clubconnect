# ClubConnect Entity Relationship Diagram

The database contains exactly five related tables. Users may belong to many clubs and register for many events through the two relationship tables.

```mermaid
erDiagram
    USERS ||--o{ CLUB_MEMBERS : joins
    CLUBS ||--o{ CLUB_MEMBERS : has
    CLUBS ||--o{ EVENTS : hosts
    USERS ||--o{ EVENT_REGISTRATIONS : makes
    EVENTS ||--o{ EVENT_REGISTRATIONS : receives

    USERS {
        BIGINT user_id PK
        VARCHAR first_name
        VARCHAR last_name
        VARCHAR email UK
        VARCHAR student_id UK
        VARCHAR password_hash
        ENUM role
        TIMESTAMP created_at
        TIMESTAMP updated_at
    }

    CLUBS {
        BIGINT club_id PK
        VARCHAR name UK
        VARCHAR category
        TEXT description
        VARCHAR contact_email
        VARCHAR contact_phone
        TIMESTAMP created_at
        TIMESTAMP updated_at
    }

    EVENTS {
        BIGINT event_id PK
        BIGINT club_id FK
        VARCHAR title
        TEXT description
        DATE event_date
        TIME event_time
        VARCHAR venue
        SMALLINT capacity
        TIMESTAMP created_at
        TIMESTAMP updated_at
    }

    CLUB_MEMBERS {
        BIGINT club_member_id PK
        BIGINT club_id FK
        BIGINT user_id FK
        TIMESTAMP joined_at
    }

    EVENT_REGISTRATIONS {
        BIGINT registration_id PK
        BIGINT event_id FK
        BIGINT user_id FK
        ENUM status
        TIMESTAMP registered_at
        TIMESTAMP updated_at
    }
```

## Relationship notes

- `events.club_id` links each event to its hosting club.
- `club_members` resolves the many-to-many relationship between students and clubs.
- `event_registrations` resolves the many-to-many relationship between students and events.
- `UNIQUE (club_id, user_id)` prevents duplicate club memberships.
- `UNIQUE (event_id, user_id)` prevents duplicate event registrations.
- Registration status is restricted to `registered`, `attended` or `cancelled`.
