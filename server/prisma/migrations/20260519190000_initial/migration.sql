-- CreateTable
CREATE TABLE `users` (
    `id` VARCHAR(191) NOT NULL,
    `username` VARCHAR(191) NOT NULL,
    `password` VARCHAR(191) NOT NULL,
    `name` VARCHAR(191) NOT NULL,
    `email` VARCHAR(191) NOT NULL DEFAULT '',
    `role` ENUM('super_admin', 'admin', 'unit_manager', 'head_of_department', 'staff') NOT NULL,
    `department` VARCHAR(191) NULL,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `lastLogin` DATETIME(3) NULL,
    `isActive` BOOLEAN NOT NULL DEFAULT true,
    `avatar` TEXT NULL,
    `tenantId` VARCHAR(191) NULL,
    UNIQUE INDEX `users_username_key`(`username`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `surveys` (
    `id` VARCHAR(191) NOT NULL,
    `title` VARCHAR(191) NOT NULL,
    `description` TEXT NOT NULL,
    `isActive` BOOLEAN NOT NULL DEFAULT true,
    `requireName` BOOLEAN NOT NULL DEFAULT false,
    `requirePhone` BOOLEAN NOT NULL DEFAULT false,
    `assignedDepartments` JSON NULL,
    `tips` JSON NULL,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `tenantId` VARCHAR(191) NULL,
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `survey_sections` (
    `id` VARCHAR(191) NOT NULL,
    `surveyId` VARCHAR(191) NOT NULL,
    `title` VARCHAR(191) NOT NULL,
    `description` TEXT NOT NULL,
    `icon` VARCHAR(191) NOT NULL DEFAULT 'clipboard-check',
    `sortOrder` INTEGER NOT NULL DEFAULT 0,
    INDEX `survey_sections_surveyId_idx`(`surveyId`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `survey_questions` (
    `id` VARCHAR(191) NOT NULL,
    `sectionId` VARCHAR(191) NOT NULL,
    `type` ENUM('rating', 'stars', 'emoji', 'text', 'multiple_choice', 'yes_no', 'nps') NOT NULL DEFAULT 'stars',
    `title` TEXT NOT NULL,
    `description` TEXT NULL,
    `required` BOOLEAN NOT NULL DEFAULT false,
    `category` VARCHAR(191) NOT NULL DEFAULT '',
    `options` JSON NULL,
    `followUp` JSON NULL,
    `sortOrder` INTEGER NOT NULL DEFAULT 0,
    INDEX `survey_questions_sectionId_idx`(`sectionId`),
    INDEX `survey_questions_type_idx`(`type`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `survey_responses` (
    `id` VARCHAR(191) NOT NULL,
    `surveyId` VARCHAR(191) NOT NULL,
    `answers` JSON NOT NULL,
    `patientName` VARCHAR(191) NULL,
    `patientPhone` VARCHAR(191) NULL,
    `ageGroup` VARCHAR(191) NULL,
    `gender` VARCHAR(191) NULL,
    `visitType` VARCHAR(191) NULL,
    `department` VARCHAR(191) NOT NULL,
    `overallScore` INTEGER NOT NULL,
    `submittedAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `tenantId` VARCHAR(191) NULL,
    INDEX `survey_responses_department_idx`(`department`),
    INDEX `survey_responses_submittedAt_idx`(`submittedAt`),
    INDEX `survey_responses_patientName_idx`(`patientName`),
    INDEX `survey_responses_patientPhone_idx`(`patientPhone`),
    INDEX `survey_responses_overallScore_idx`(`overallScore`),
    INDEX `survey_responses_surveyId_idx`(`surveyId`),
    INDEX `survey_responses_department_submittedAt_idx`(`department`, `submittedAt`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `tickets` (
    `id` VARCHAR(191) NOT NULL,
    `responseId` VARCHAR(191) NOT NULL,
    `department` VARCHAR(191) NOT NULL,
    `patientName` VARCHAR(191) NOT NULL,
    `patientPhone` VARCHAR(191) NULL,
    `priority` ENUM('high', 'medium', 'low') NOT NULL,
    `status` ENUM('open', 'in_progress', 'resolved') NOT NULL DEFAULT 'open',
    `description` TEXT NOT NULL,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `resolvedAt` DATETIME(3) NULL,
    `resolutionNotes` TEXT NULL,
    `assignedTo` VARCHAR(191) NULL,
    UNIQUE INDEX `tickets_responseId_key`(`responseId`),
    INDEX `tickets_status_idx`(`status`),
    INDEX `tickets_department_idx`(`department`),
    INDEX `tickets_createdAt_idx`(`createdAt`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `audit_logs` (
    `id` VARCHAR(191) NOT NULL,
    `userId` VARCHAR(191) NOT NULL,
    `action` VARCHAR(191) NOT NULL,
    `details` TEXT NOT NULL,
    `timestamp` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    INDEX `audit_logs_userId_idx`(`userId`),
    INDEX `audit_logs_timestamp_idx`(`timestamp`),
    INDEX `audit_logs_action_idx`(`action`),
    INDEX `audit_logs_userId_timestamp_idx`(`userId`, `timestamp`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `settings` (
    `id` VARCHAR(191) NOT NULL,
    `tenantId` VARCHAR(191) NULL,
    `data` JSON NOT NULL,
    UNIQUE INDEX `settings_tenantId_key`(`tenantId`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `tenants` (
    `id` VARCHAR(191) NOT NULL,
    `name` VARCHAR(191) NOT NULL,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `survey_answers` (
    `id` VARCHAR(191) NOT NULL,
    `responseId` VARCHAR(191) NOT NULL,
    `questionId` VARCHAR(191) NOT NULL,
    `value` TEXT NOT NULL,
    INDEX `survey_answers_questionId_idx`(`questionId`),
    INDEX `survey_answers_responseId_idx`(`responseId`),
    UNIQUE INDEX `survey_answers_responseId_questionId_key`(`responseId`, `questionId`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `archived_survey_responses` (
    `id` VARCHAR(191) NOT NULL,
    `surveyId` VARCHAR(191) NOT NULL,
    `answers` JSON NOT NULL,
    `patientName` VARCHAR(191) NULL,
    `patientPhone` VARCHAR(191) NULL,
    `ageGroup` VARCHAR(191) NULL,
    `gender` VARCHAR(191) NULL,
    `visitType` VARCHAR(191) NULL,
    `department` VARCHAR(191) NOT NULL,
    `overallScore` INTEGER NOT NULL,
    `submittedAt` DATETIME(3) NOT NULL,
    `archivedAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    INDEX `archived_survey_responses_department_idx`(`department`),
    INDEX `archived_survey_responses_submittedAt_idx`(`submittedAt`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `archived_audit_logs` (
    `id` VARCHAR(191) NOT NULL,
    `userId` VARCHAR(191) NOT NULL,
    `action` VARCHAR(191) NOT NULL,
    `details` TEXT NOT NULL,
    `timestamp` DATETIME(3) NOT NULL,
    `archivedAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    INDEX `archived_audit_logs_userId_idx`(`userId`),
    INDEX `archived_audit_logs_timestamp_idx`(`timestamp`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `error_logs` (
    `id` VARCHAR(191) NOT NULL,
    `level` VARCHAR(191) NOT NULL DEFAULT 'error',
    `message` TEXT NOT NULL,
    `stack` TEXT NULL,
    `source` VARCHAR(191) NULL,
    `metadata` JSON NULL,
    `status` VARCHAR(191) NOT NULL DEFAULT 'new',
    `resolutionNotes` TEXT NULL,
    `count` INTEGER NOT NULL DEFAULT 1,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `resolvedAt` DATETIME(3) NULL,
    `userId` VARCHAR(191) NULL,
    INDEX `error_logs_level_idx`(`level`),
    INDEX `error_logs_status_idx`(`status`),
    INDEX `error_logs_source_idx`(`source`),
    INDEX `error_logs_createdAt_idx`(`createdAt`),
    INDEX `error_logs_level_status_idx`(`level`, `status`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `refresh_tokens` (
    `id` VARCHAR(191) NOT NULL,
    `token` VARCHAR(500) NOT NULL,
    `userId` VARCHAR(191) NOT NULL,
    `expiresAt` DATETIME(3) NOT NULL,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    UNIQUE INDEX `refresh_tokens_token_key`(`token`),
    INDEX `refresh_tokens_userId_idx`(`userId`),
    INDEX `refresh_tokens_expiresAt_idx`(`expiresAt`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- AddForeignKey
ALTER TABLE `users` ADD CONSTRAINT `users_tenantId_fkey` FOREIGN KEY (`tenantId`) REFERENCES `tenants`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `surveys` ADD CONSTRAINT `surveys_tenantId_fkey` FOREIGN KEY (`tenantId`) REFERENCES `tenants`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `survey_sections` ADD CONSTRAINT `survey_sections_surveyId_fkey` FOREIGN KEY (`surveyId`) REFERENCES `surveys`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `survey_questions` ADD CONSTRAINT `survey_questions_sectionId_fkey` FOREIGN KEY (`sectionId`) REFERENCES `survey_sections`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `survey_responses` ADD CONSTRAINT `survey_responses_tenantId_fkey` FOREIGN KEY (`tenantId`) REFERENCES `tenants`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `survey_responses` ADD CONSTRAINT `survey_responses_surveyId_fkey` FOREIGN KEY (`surveyId`) REFERENCES `surveys`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `tickets` ADD CONSTRAINT `tickets_responseId_fkey` FOREIGN KEY (`responseId`) REFERENCES `survey_responses`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `audit_logs` ADD CONSTRAINT `audit_logs_userId_fkey` FOREIGN KEY (`userId`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `settings` ADD CONSTRAINT `settings_tenantId_fkey` FOREIGN KEY (`tenantId`) REFERENCES `tenants`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `survey_answers` ADD CONSTRAINT `survey_answers_responseId_fkey` FOREIGN KEY (`responseId`) REFERENCES `survey_responses`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `survey_answers` ADD CONSTRAINT `survey_answers_questionId_fkey` FOREIGN KEY (`questionId`) REFERENCES `survey_questions`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `refresh_tokens` ADD CONSTRAINT `refresh_tokens_userId_fkey` FOREIGN KEY (`userId`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
