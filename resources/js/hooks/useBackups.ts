import { useState, useEffect, useCallback } from "react";
import { backupsAPI } from "../api/client";

interface BackupFile {
  filename: string;
  sizeBytes: number;
  sizeMb: number;
  createdAt: string;
  modifiedAt: string;
}

interface BackupConfig {
  enabled: boolean;
  retentionDays: number;
  schedule: string;
  compressGzip: boolean;
}

interface BackupListResponse {
  backups: BackupFile[];
  config: BackupConfig;
}

interface BackupVerification {
  valid: boolean;
  filename: string;
  sizeBytes: number;
  sizeMb: number;
  tableCount: number;
  hasData: boolean;
  estimatedRows: number;
  error: string | null;
  checkedAt: string;
}

export function useBackups() {
  const [data, setData] = useState<BackupListResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [creating, setCreating] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchBackups = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const result = await backupsAPI.getAll();
      setData(result as BackupListResponse);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : "Failed to load backups";
      setError(message);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchBackups();
  }, [fetchBackups]);

  const create = useCallback(async () => {
    setCreating(true);
    setError(null);
    try {
      const result = await backupsAPI.create();
      await fetchBackups();
      return result;
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : "Failed to create backup";
      setError(message);
      throw err;
    } finally {
      setCreating(false);
    }
  }, [fetchBackups]);

  const deleteBackup = useCallback(async (filename: string) => {
    setError(null);
    try {
      await backupsAPI.delete(filename);
      await fetchBackups();
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : "Failed to delete backup";
      setError(message);
      throw err;
    }
  }, [fetchBackups]);

  const verify = useCallback(async (filename: string): Promise<BackupVerification> => {
    setError(null);
    try {
      const result = await backupsAPI.verify(filename);
      return result as BackupVerification;
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : "Failed to verify backup";
      setError(message);
      throw err;
    }
  }, []);

  const restore = useCallback(async (filename: string) => {
    setError(null);
    try {
      await backupsAPI.restore(filename);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : "Failed to restore backup";
      setError(message);
      throw err;
    }
  }, []);

  return {
    data,
    loading,
    creating,
    error,
    refresh: fetchBackups,
    create,
    delete: deleteBackup,
    verify,
    restore,
  };
}
