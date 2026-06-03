# Database Index Audit - MedSurvey Pro

## 1. Purpose

This document audits current database migrations before adding performance indexes.

No migration or source code changes were made in this step.

---

## 2. Tables Reviewed

- users
- surveys
- survey_sections
- survey_questions
- survey_responses
- survey_answers
- tickets
- settings
- audit_logs
- error_logs
- archived_survey_responses
- archived_audit_logs
- refresh_tokens
- tenants

---

## 3. Existing Indexes / Constraints

### tenants

Columns:

- `id` (string, PK)
- `name` (string)
- `createdAt` (timestamp, useCurrent)

Existing indexes:

- PRIMARY KEY (`id`)

---

### users

Columns:

- `id` (string, PK)
- `username` (string, unique)
- `password` (string)
- `name` (string)
- `email` (string, default '')
- `role` (enum: super_admin, admin, unit_manager, head_of_department, staff)
- `department` (string, nullable)
- `createdAt` (timestamp, useCurrent)
- `lastLogin` (timestamp, nullable)
- `isActive` (boolean, default true)
- `avatar` (text, nullable)
- `tenantId` (string, nullable, FK → tenants)

Existing indexes:

- PRIMARY KEY (`id`)
- UNIQUE (`username`)
- Foreign KEY (`tenantId` → tenants.id)

Missing:

- Index on `role`
- Index on `tenantId`
- Index on `isActive`
- Composite index `tenantId + isActive`

---

### settings

Columns:

- `id` (string, PK)
- `tenantId` (string, nullable, UNIQUE, FK → tenants)
- `data` (json)

Existing indexes:

- PRIMARY KEY (`id`)
- UNIQUE (`tenantId`)
- Foreign KEY (`tenantId` → tenants.id)

---

### surveys

Columns:

- `id` (string, PK)
- `title` (string)
- `description` (text)
- `isActive` (boolean, default true)
- `requireName` (boolean, default false)
- `requirePhone` (boolean, default false)
- `assignedDepartments` (json, nullable)
- `tips` (json, nullable)
- `createdAt` (timestamp, useCurrent)
- `tenantId` (string, nullable, FK → tenants)

Existing indexes:

- PRIMARY KEY (`id`)
- Foreign KEY (`tenantId` → tenants.id)

Missing:

- Index on `isActive`
- Index on `tenantId`
- Index on `createdAt`
- Composite index `tenantId + isActive`
- Composite index `isActive + createdAt`

---

### survey_sections

Columns:

- `id` (string, PK)
- `surveyId` (string, FK → surveys)
- `title` (string)
- `description` (text)
- `icon` (string, default 'clipboard-check')
- `sortOrder` (integer, default 0)

Existing indexes:

- PRIMARY KEY (`id`)
- INDEX (`surveyId`)
- Foreign KEY (`surveyId` → surveys.id, cascadeOnDelete)

Missing:

- Composite index `surveyId + sortOrder`

---

### survey_questions

Columns:

- `id` (string, PK)
- `sectionId` (string, FK → survey_sections)
- `type` (enum: rating, stars, emoji, text, multiple_choice, yes_no, nps, default 'stars')
- `title` (text)
- `description` (text, nullable)
- `required` (boolean, default false)
- `category` (string, default '')
- `options` (json, nullable)
- `followUp` (json, nullable)
- `sortOrder` (integer, default 0)

Existing indexes:

- PRIMARY KEY (`id`)
- INDEX (`sectionId`)
- INDEX (`type`)
- Foreign KEY (`sectionId` → survey_sections.id, cascadeOnDelete)

Missing:

- Composite index `sectionId + sortOrder`

---

### survey_responses

Columns:

- `id` (string, PK)
- `surveyId` (string, FK → surveys)
- `answers` (json)
- `patientName` (string, nullable)
- `patientPhone` (string, nullable)
- `ageGroup` (string, nullable)
- `gender` (string, nullable)
- `visitType` (string, nullable)
- `department` (string)
- `overallScore` (integer)
- `submittedAt` (timestamp, useCurrent)
- `tenantId` (string, nullable, FK → tenants)

Existing indexes:

- PRIMARY KEY (`id`)
- INDEX (`department`)
- INDEX (`submittedAt`)
- INDEX (`patientName`)
- INDEX (`patientPhone`)
- INDEX (`overallScore`)
- INDEX (`surveyId`)
- INDEX (`department` + `submittedAt`)
- INDEX (`tenantId` + `submittedAt`) — added later (migration 000004)
- INDEX (`tenantId` + `department` + `submittedAt`) — added later (migration 000004)
- Foreign KEY (`tenantId` → tenants.id)
- Foreign KEY (`surveyId` → surveys.id)

Missing:

- Composite index `overallScore + submittedAt`
- Composite index `gender + submittedAt`
- Composite index `visitType + submittedAt`
- Composite index `ageGroup + submittedAt`
- Index on `patientPhone` (exists but as single column)

---

### survey_answers

Columns:

- `id` (string, PK)
- `responseId` (string, FK → survey_responses)
- `questionId` (string, FK → survey_questions)
- `value` (text)

Existing indexes:

- PRIMARY KEY (`id`)
- UNIQUE (`responseId` + `questionId`)
- INDEX (`questionId`)
- INDEX (`responseId`)
- Foreign KEY (`responseId` → survey_responses.id, cascadeOnDelete)
- Foreign KEY (`questionId` → survey_questions.id, cascadeOnDelete)

---

### tickets

Columns:

- `id` (string, PK)
- `responseId` (string, UNIQUE, FK → survey_responses)
- `department` (string)
- `patientName` (string)
- `patientPhone` (string, nullable)
- `priority` (enum: high, medium, low)
- `status` (enum: open, in_progress, resolved, default 'open')
- `description` (text)
- `createdAt` (timestamp, useCurrent)
- `resolvedAt` (timestamp, nullable)
- `resolutionNotes` (text, nullable)
- `assignedTo` (string, nullable)

Existing indexes:

- PRIMARY KEY (`id`)
- UNIQUE (`responseId`)
- INDEX (`status`)
- INDEX (`department`)
- INDEX (`createdAt`)
- INDEX (`priority`)
- INDEX (`assignedTo`)
- INDEX (`department` + `status` + `createdAt`) — added later (migration 000004)
- Foreign KEY (`responseId` → survey_responses.id, cascadeOnDelete)

Missing:

- Composite index `priority + status`
- Index on `resolvedAt`

---

### audit_logs

Columns:

- `id` (string, PK)
- `userId` (string, nullable, FK → users)
- `action` (string)
- `details` (text)
- `ipAddress` (string, nullable, added later)
- `userAgent` (text, nullable, added later)
- `deviceName` (string, nullable, added later)
- `timestamp` (timestamp, useCurrent)

Existing indexes:

- PRIMARY KEY (`id`)
- INDEX (`userId`)
- INDEX (`timestamp`)
- INDEX (`action`)
- INDEX (`userId` + `timestamp`)
- Foreign KEY (`userId` → users.id, cascadeOnDelete)

---

### error_logs

Columns:

- `id` (string, PK)
- `level` (string, default 'error')
- `message` (text)
- `stack` (text, nullable)
- `source` (string, nullable)
- `metadata` (json, nullable)
- `status` (string, default 'new')
- `resolutionNotes` (text, nullable)
- `count` (integer, default 1)
- `createdAt` (timestamp, useCurrent)
- `resolvedAt` (timestamp, nullable)
- `userId` (string, nullable)

Existing indexes:

- PRIMARY KEY (`id`)
- INDEX (`level`)
- INDEX (`status`)
- INDEX (`source`)
- INDEX (`createdAt`)
- INDEX (`level` + `status`)

---

### refresh_tokens

Columns:

- `id` (string, PK)
- `token` (string, UNIQUE)
- `userId` (string, FK → users)
- `expiresAt` (timestamp)
- `createdAt` (timestamp, useCurrent)

Existing indexes:

- PRIMARY KEY (`id`)
- UNIQUE (`token`)
- INDEX (`userId`)
- INDEX (`expiresAt`)
- Foreign KEY (`userId` → users.id, cascadeOnDelete)

---

### archived_survey_responses

Columns:

- `id` (string, PK)
- `surveyId` (string)
- `answers` (json)
- `patientName` (string, nullable)
- `patientPhone` (string, nullable)
- `ageGroup` (string, nullable)
- `gender` (string, nullable)
- `visitType` (string, nullable)
- `department` (string)
- `overallScore` (integer)
- `submittedAt` (timestamp)
- `archivedAt` (timestamp, useCurrent)

Existing indexes:

- PRIMARY KEY (`id`)
- INDEX (`department`)
- INDEX (`submittedAt`)

---

### archived_audit_logs

Columns:

- `id` (string, PK)
- `userId` (string)
- `action` (string)
- `details` (text)
- `ipAddress` (string, nullable, added later)
- `userAgent` (text, nullable, added later)
- `deviceName` (string, nullable, added later)
- `timestamp` (timestamp)
- `archivedAt` (timestamp, useCurrent)

Existing indexes:

- PRIMARY KEY (`id`)
- INDEX (`userId`)
- INDEX (`timestamp`)

---

## 4. Performance-Critical Query Patterns

Based on the codebase, likely query patterns:

### survey_responses

Likely filtered by:

- tenantId
- department
- submittedAt
- overallScore
- gender
- visitType
- ageGroup
- patientName
- patientPhone

### survey_answers

Likely filtered by:

- responseId
- questionId

### tickets

Likely filtered by:

- department
- status
- priority
- responseId
- createdAt

### surveys

Likely filtered by:

- tenantId
- isActive
- createdAt

### survey_sections / survey_questions

Likely ordered by:

- sortOrder

---

## 5. Recommended Indexes To Add Later

Do not create them yet. Only list recommended indexes.

Recommended candidates:

### survey_responses

- `overallScore + submittedAt` — for score trend queries
- `gender + submittedAt` — for gender-based analytics
- `visitType + submittedAt` — for visit type analytics
- `ageGroup + submittedAt` — for age group analytics

### survey_answers

- (none — current indexes are sufficient)

### tickets

- `priority + status` — for priority-based ticket views
- `resolvedAt` — for resolution time analytics

### surveys

- `tenantId + isActive` — for tenant-scoped active surveys
- `isActive + createdAt` — for listing active surveys sorted by date

### survey_sections

- `surveyId + sortOrder` — for ordered section loading

### survey_questions

- `sectionId + sortOrder` — for ordered question loading

### users

- `role` — for role-based filtering
- `tenantId` — for tenant-scoped user queries
- `isActive` — for active user queries
- `tenantId + isActive` — for tenant-scoped active users

### error_logs

- `createdAt` — already indexed
- (none additional needed)

### audit_logs

- (none — current indexes are sufficient for dashboard filtering)

---

## 6. Risk Notes

Before adding indexes:

- Avoid duplicate index names. The migration `2026_06_01_000001` already uses names like `survey_responses_tenant_submitted_idx` and `tickets_dept_status_created_idx`.
- Avoid adding indexes already created by foreign keys — Laravel does not auto-index foreign keys, but the migrations explicitly add them.
- Keep index names explicit to prevent MySQL duplicate constraint/index name issues. Use consistent naming convention: `{table}_{column1}_{column2}_idx`.
- Add rollback-safe `down()` method that calls `$table->dropIndex('index_name')`.
- Index on `patientPhone` already exists — no need to add again.
- Review the `SurveyResponse` model and controller queries before finalizing index decisions.
- Archived tables (`archived_survey_responses`, `archived_audit_logs`) are append-only and rarely queried — focus indexes on main tables.
