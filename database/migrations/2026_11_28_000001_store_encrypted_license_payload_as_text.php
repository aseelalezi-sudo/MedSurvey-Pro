<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // License claims are inspected before DDL. Run with application writers drained.
        $updates = [];
        foreach (DB::table('license_states')->select(['id', 'payload'])->whereNotNull('payload')->cursor() as $row) {
            $normalized = $this->normalize($row->payload);
            if ($row->payload !== $normalized) {
                $updates[$row->id] = $normalized;
            }
        }

        Schema::table('license_states', function (Blueprint $table): void {
            $table->longText('payload')->nullable()->change();
        });

        DB::transaction(function () use ($updates): void {
            foreach ($updates as $id => $payload) {
                DB::table('license_states')->where('id', $id)->update(['payload' => $payload]);
            }
        });
    }

    private function normalize(string $stored): ?string
    {
        $legacy = json_decode($stored, true);

        if ($stored === 'null') {
            return null;
        }
        if (is_array($legacy)) {
            return Crypt::encryptString($stored);
        }

        $ciphertext = is_string($legacy) ? $legacy : $stored;

        try {
            $claims = json_decode(Crypt::decryptString($ciphertext), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            throw new RuntimeException('A legacy license payload cannot be decoded with the current application keys.');
        }

        if (! is_array($claims)) {
            throw new RuntimeException('A legacy license payload has an unsupported structure.');
        }

        return $ciphertext;
    }

    public function down(): void
    {
        // Older application code can read this type. Converting ciphertext back
        // to JSON would reject valid rows. Restore a pre-migration backup for schema rollback.
    }
};
