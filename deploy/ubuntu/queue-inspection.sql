-- Use a SELECT-only connection to the verified application database.
-- Repeat before and after an observation interval with known incoming submissions.
START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY;
SELECT COUNT(*) AS outstanding, COUNT(reserved_at) AS reserved,
       MIN(created_at) AS oldest_created_unix,
       COUNT(CASE WHEN queue = 'medsurvey-live' THEN 1 END) AS proposed_queue_rows FROM jobs;
SELECT COUNT(*) AS failed FROM failed_jobs;
-- Laravel stores the event class in displayName for BroadcastEvent. Only
-- compare known metadata. Never return a free-form label or data.command.
WITH metadata AS (
    SELECT attempts, created_at, reserved_at, available_at,
           JSON_EXTRACT(IF(JSON_VALID(payload), payload, '{}'), '$.data.commandName') AS command_name,
           JSON_EXTRACT(IF(JSON_VALID(payload), payload, '{}'), '$.displayName') AS display_name
    FROM jobs WHERE queue = 'default'
), classified AS (
    SELECT attempts, CAST(UNIX_TIMESTAMP() AS SIGNED) - CAST(created_at AS SIGNED) AS age_s,
           CASE WHEN reserved_at IS NULL THEN 'unreserved'
                WHEN reserved_at <= UNIX_TIMESTAMP() - 90 THEN 'expired'
                ELSE 'reserved' END AS reservation,
           CASE WHEN reserved_at IS NOT NULL THEN reserved_at <= UNIX_TIMESTAMP() - 90
                ELSE available_at <= UNIX_TIMESTAMP() END AS runnable,
           CASE
             WHEN JSON_UNQUOTE(command_name) = CONCAT_WS(CHAR(92),'Illuminate','Broadcasting','BroadcastEvent')
             THEN CASE WHEN JSON_UNQUOTE(display_name) = CONCAT_WS(CHAR(92),'App','Events','SurveySubmitted')
                       THEN 'broadcast:SurveySubmitted'
                       WHEN JSON_TYPE(display_name) = 'STRING'
                       THEN CONCAT('unclassified-event:', LEFT(SHA2(JSON_UNQUOTE(display_name),256),16))
                       ELSE 'broadcast:unclassified-event' END
             WHEN JSON_UNQUOTE(command_name) = CONCAT_WS(CHAR(92),'Illuminate','Notifications','SendQueuedNotifications')
             THEN 'notification:SendQueuedNotifications'
             WHEN JSON_UNQUOTE(command_name) = CONCAT_WS(CHAR(92),'Illuminate','Mail','SendQueuedMailable')
             THEN 'mail:SendQueuedMailable'
             WHEN JSON_TYPE(command_name) = 'STRING'
             THEN CONCAT('unclassified-job:', LEFT(SHA2(JSON_UNQUOTE(command_name),256),16))
             ELSE 'unclassified-envelope'
           END AS declared_type
    FROM metadata
)
SELECT declared_type, attempts, reservation, runnable, COUNT(*) AS jobs,
       MIN(age_s) AS youngest_seconds, MAX(age_s) AS oldest_seconds
FROM classified GROUP BY declared_type, attempts, reservation, runnable
ORDER BY declared_type, attempts, reservation, runnable;
COMMIT;
