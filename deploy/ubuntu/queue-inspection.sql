-- Use a SELECT-only connection to the verified application database.
-- Repeat before and after an observation interval with known incoming submissions.
START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY;
SELECT COUNT(*) AS outstanding, COUNT(reserved_at) AS reserved,
       MIN(created_at) AS oldest_created_unix,
       COUNT(CASE WHEN queue = 'medsurvey-live' THEN 1 END) AS proposed_queue_rows FROM jobs;
SELECT COUNT(*) AS failed FROM failed_jobs;
COMMIT;
