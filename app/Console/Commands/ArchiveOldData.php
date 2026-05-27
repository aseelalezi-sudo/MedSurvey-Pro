<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\SurveyResponse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArchiveOldData extends Command
{
    protected $signature = 'archive:old-data {--years=3 : Archive records older than this number of years}';

    protected $description = 'Archive survey responses and audit logs older than the configured retention period';

    public function handle(): int
    {
        $years = max(1, (int) $this->option('years'));
        $cutoff = now()->subYears($years);
        $archivedResponses = 0;
        $archivedAuditLogs = 0;

        SurveyResponse::query()
            ->where('submittedAt', '<', $cutoff)
            ->orderBy('id')
            ->chunk(500, function ($responses) use (&$archivedResponses): void {
                DB::transaction(function () use ($responses, &$archivedResponses): void {
                    $rows = $responses->map(fn (SurveyResponse $response) => [
                        'id' => $response->id,
                        'surveyId' => $response->surveyId,
                        'answers' => json_encode($response->answers ?? []),
                        'patientName' => $response->patientName,
                        'patientPhone' => $response->patientPhone,
                        'ageGroup' => $response->ageGroup,
                        'gender' => $response->gender,
                        'visitType' => $response->visitType,
                        'department' => $response->department,
                        'overallScore' => $response->overallScore,
                        'submittedAt' => $response->submittedAt,
                        'archivedAt' => now(),
                    ])->all();

                    DB::table('archived_survey_responses')->insertOrIgnore($rows);
                    SurveyResponse::query()->whereIn('id', $responses->pluck('id'))->delete();
                    $archivedResponses += count($rows);
                });
            });

        AuditLog::query()
            ->where('timestamp', '<', $cutoff)
            ->orderBy('id')
            ->chunk(500, function ($logs) use (&$archivedAuditLogs): void {
                DB::transaction(function () use ($logs, &$archivedAuditLogs): void {
                    $rows = $logs->map(fn (AuditLog $log) => [
                        'id' => $log->id,
                        'userId' => $log->userId,
                        'action' => $log->action,
                        'details' => $log->details,
                        'timestamp' => $log->timestamp,
                        'archivedAt' => now(),
                    ])->all();

                    DB::table('archived_audit_logs')->insertOrIgnore($rows);
                    AuditLog::query()->whereIn('id', $logs->pluck('id'))->delete();
                    $archivedAuditLogs += count($rows);
                });
            });

        $this->info("Archived {$archivedResponses} survey response(s) and {$archivedAuditLogs} audit log(s).");

        return self::SUCCESS;
    }
}
