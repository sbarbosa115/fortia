<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260930162524 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE api_key (status VARCHAR(10) NOT NULL, last_used_at DATETIME DEFAULT NULL, id VARCHAR(64) NOT NULL, customer_id VARCHAR(16) NOT NULL, name VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL, expires_at DATETIME DEFAULT NULL, INDEX idx_api_key_customer (customer_id, status, created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE app_setting (setting_key VARCHAR(100) NOT NULL, value JSON NOT NULL, PRIMARY KEY (setting_key)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE app_user (password_hash VARCHAR(255) DEFAULT NULL, google_subject VARCHAR(255) DEFAULT NULL, last_signed_in_at DATETIME DEFAULT NULL, updated_at DATETIME NOT NULL, id VARCHAR(36) NOT NULL, email VARCHAR(180) NOT NULL, name VARCHAR(50) NOT NULL, customer_id VARCHAR(16) NOT NULL, root TINYINT NOT NULL, user_groups JSON NOT NULL, created_at DATETIME NOT NULL, INDEX idx_user_customer (customer_id), UNIQUE INDEX uniq_user_email (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE assignation (description LONGTEXT DEFAULT NULL, max_follow_ups INT NOT NULL, active TINYINT NOT NULL, due_date VARCHAR(10) DEFAULT NULL, audience JSON NOT NULL, questions JSON NOT NULL, project_id VARCHAR(36) DEFAULT NULL, shared_session_id VARCHAR(36) DEFAULT NULL, attempts JSON NOT NULL, last_reminder_sent_at DATETIME DEFAULT NULL, updated_at DATETIME NOT NULL, assignations_id VARCHAR(36) NOT NULL, customer_id VARCHAR(16) NOT NULL, organization_id VARCHAR(36) NOT NULL, questionnaire_id VARCHAR(36) NOT NULL, name VARCHAR(200) NOT NULL, type VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL, INDEX idx_assignation_customer (customer_id, created_at), INDEX idx_assignation_questionnaire (questionnaire_id), INDEX idx_assignation_project (project_id), INDEX idx_assignation_organization (organization_id), PRIMARY KEY (assignations_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE assignation_answer (assignations_id VARCHAR(36) NOT NULL, organization_user_id VARCHAR(36) NOT NULL, session_id VARCHAR(36) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (assignations_id, organization_user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE customer (settings JSON NOT NULL, onboarding_completed TINYINT DEFAULT NULL, workspace_name VARCHAR(120) DEFAULT NULL, website VARCHAR(2048) DEFAULT NULL, updated_at DATETIME NOT NULL, customer_id VARCHAR(16) NOT NULL, language VARCHAR(5) NOT NULL, source VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, INDEX idx_customer_source (source, created_at), PRIMARY KEY (customer_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE customer_plan (stripe_customer_id VARCHAR(255) DEFAULT NULL, stripe_subscription_id VARCHAR(255) DEFAULT NULL, trial_end DATETIME DEFAULT NULL, discount JSON DEFAULT NULL, trial_used_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, customer_id VARCHAR(16) NOT NULL, plan_id VARCHAR(100) NOT NULL, from_at VARCHAR(10) NOT NULL, to_at VARCHAR(10) NOT NULL, billing_interval VARCHAR(5) NOT NULL, INDEX idx_customer_plan_subscription (stripe_subscription_id), PRIMARY KEY (customer_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE customer_styles (updated_at DATETIME NOT NULL, customer_id VARCHAR(16) NOT NULL, website VARCHAR(2048) DEFAULT NULL, styles JSON NOT NULL, PRIMARY KEY (customer_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE diagnostic (id VARCHAR(36) NOT NULL, questionnaire_id VARCHAR(36) NOT NULL, tiers JSON NOT NULL, recommendations JSON NOT NULL, action_plan JSON NOT NULL, UNIQUE INDEX uniq_diagnostic_questionnaire (questionnaire_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE domain_event_log (id BIGINT AUTO_INCREMENT NOT NULL, event_type VARCHAR(64) NOT NULL, customer_id VARCHAR(16) DEFAULT NULL, feature VARCHAR(64) DEFAULT NULL, payload JSON NOT NULL, occurred_at DATETIME NOT NULL, INDEX idx_event_customer_type (customer_id, event_type, occurred_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE feature (updated_at DATETIME NOT NULL, id VARCHAR(100) NOT NULL, feature_name VARCHAR(100) NOT NULL, feature_description LONGTEXT NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE flow (detail LONGTEXT NOT NULL, source_url VARCHAR(768) DEFAULT NULL, states JSON NOT NULL, cta JSON DEFAULT NULL, layout JSON DEFAULT NULL, result_copy JSON DEFAULT NULL, updated_at DATETIME NOT NULL, id VARCHAR(20) NOT NULL, slug VARCHAR(100) NOT NULL, customer_id VARCHAR(16) NOT NULL, questionnaire_id VARCHAR(36) NOT NULL, created_at DATETIME NOT NULL, INDEX idx_flow_source_url (source_url), UNIQUE INDEX uniq_flow_slug (slug), UNIQUE INDEX uniq_flow_questionnaire (questionnaire_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE impersonation_log (id BIGINT AUTO_INCREMENT NOT NULL, admin_email VARCHAR(180) NOT NULL, customer_id VARCHAR(16) NOT NULL, method VARCHAR(10) NOT NULL, path VARCHAR(512) NOT NULL, occurred_at DATETIME NOT NULL, INDEX idx_impersonation_customer (customer_id, occurred_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE job (status VARCHAR(12) NOT NULL, result JSON DEFAULT NULL, stage VARCHAR(64) DEFAULT NULL, updated_at DATETIME NOT NULL, job_id VARCHAR(40) NOT NULL, job_type VARCHAR(40) NOT NULL, payload JSON NOT NULL, customer_id VARCHAR(16) DEFAULT NULL, created_at DATETIME NOT NULL, INDEX idx_job_type (job_type, created_at), INDEX idx_job_status (status, created_at), PRIMARY KEY (job_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE organization (domain_email VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, active TINYINT NOT NULL, updated_at DATETIME NOT NULL, organization_id VARCHAR(36) NOT NULL, customer_id VARCHAR(16) NOT NULL, name VARCHAR(120) NOT NULL, created_at DATETIME NOT NULL, INDEX idx_organization_customer (customer_id, created_at), UNIQUE INDEX uniq_organization_domain (domain_email), PRIMARY KEY (organization_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE organization_user (name VARCHAR(200) NOT NULL, email VARCHAR(255) DEFAULT NULL, phone VARCHAR(50) DEFAULT NULL, role VARCHAR(120) DEFAULT NULL, area VARCHAR(120) DEFAULT NULL, updated_at DATETIME NOT NULL, organization_user_id VARCHAR(36) NOT NULL, organization_id VARCHAR(36) NOT NULL, created_at DATETIME NOT NULL, INDEX idx_member_organization (organization_id), INDEX idx_member_email (email), INDEX idx_member_phone (phone), UNIQUE INDEX uniq_member_email (organization_id, email), PRIMARY KEY (organization_user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE password_reset_code (attempts INT NOT NULL, used_at DATETIME DEFAULT NULL, id VARCHAR(36) NOT NULL, email VARCHAR(180) NOT NULL, code_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL, INDEX idx_reset_email (email, created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE plan (plan_name VARCHAR(100) NOT NULL, plan_description LONGTEXT NOT NULL, features JSON NOT NULL, max_questionnaires INT DEFAULT NULL, max_responses INT DEFAULT NULL, price_amount INT DEFAULT NULL, currency VARCHAR(3) NOT NULL, stripe_price_id VARCHAR(255) DEFAULT NULL, yearly_price_amount INT DEFAULT NULL, stripe_yearly_price_id VARCHAR(255) DEFAULT NULL, trial_days INT NOT NULL, updated_at DATETIME NOT NULL, id VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX uniq_plan_name (plan_name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE product (description LONGTEXT NOT NULL, price NUMERIC(12, 2) DEFAULT NULL, image_url VARCHAR(2048) DEFAULT NULL, product_url VARCHAR(2048) DEFAULT NULL, source_url VARCHAR(768) DEFAULT NULL, questionnaire_id VARCHAR(36) DEFAULT NULL, updated_at DATETIME NOT NULL, product_id VARCHAR(36) NOT NULL, customer_id VARCHAR(16) NOT NULL, name VARCHAR(500) NOT NULL, created_at DATETIME NOT NULL, INDEX idx_product_customer (customer_id), INDEX idx_product_source (source_url), INDEX idx_product_questionnaire (questionnaire_id), PRIMARY KEY (product_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE project (description LONGTEXT DEFAULT NULL, updated_at DATETIME NOT NULL, project_id VARCHAR(36) NOT NULL, customer_id VARCHAR(16) NOT NULL, organization_id VARCHAR(36) NOT NULL, name VARCHAR(200) NOT NULL, due_date VARCHAR(10) DEFAULT NULL, created_at DATETIME NOT NULL, INDEX idx_project_customer (customer_id, created_at), PRIMARY KEY (project_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE prompt (id VARCHAR(36) NOT NULL, questionnaire_id VARCHAR(36) NOT NULL, customer_id VARCHAR(16) NOT NULL, s3_path VARCHAR(1024) NOT NULL, outcome VARCHAR(20) DEFAULT NULL, prompt_order INT NOT NULL, INDEX idx_prompt_questionnaire (questionnaire_id, prompt_order), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE questionnaire (description LONGTEXT DEFAULT NULL, disclaimer LONGTEXT DEFAULT NULL, capture_user_data TINYINT NOT NULL, landing_page TINYINT NOT NULL, is_active TINYINT NOT NULL, on_completed JSON DEFAULT NULL, parent VARCHAR(36) NOT NULL, origin_session_id VARCHAR(36) DEFAULT NULL, questions JSON NOT NULL, question_count INT NOT NULL, is_chain TINYINT NOT NULL, slug VARCHAR(100) DEFAULT NULL, updated_at DATETIME NOT NULL, questionnaire_id VARCHAR(36) NOT NULL, customer_id VARCHAR(16) NOT NULL, title LONGTEXT NOT NULL, type VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, INDEX idx_questionnaire_customer (customer_id, parent, created_at), INDEX idx_questionnaire_parent (parent), INDEX idx_questionnaire_origin_session (origin_session_id), PRIMARY KEY (questionnaire_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE questionnaire_dashboard (questionnaire_id VARCHAR(36) NOT NULL, customer_id VARCHAR(16) NOT NULL, type VARCHAR(20) NOT NULL, charts JSON NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (questionnaire_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE questionnaire_session (status VARCHAR(12) NOT NULL, ended_at DATETIME DEFAULT NULL, flow_id VARCHAR(20) DEFAULT NULL, user_data JSON DEFAULT NULL, assignations_id VARCHAR(36) DEFAULT NULL, organization_user_id VARCHAR(36) DEFAULT NULL, assignation_type VARCHAR(12) DEFAULT NULL, attempt INT NOT NULL, updated_at DATETIME NOT NULL, session_id VARCHAR(36) NOT NULL, questionnaire_id VARCHAR(36) NOT NULL, customer_id VARCHAR(16) NOT NULL, document JSON NOT NULL, started_at DATETIME NOT NULL, INDEX idx_session_questionnaire (questionnaire_id, started_at), INDEX idx_session_assignation (assignations_id, organization_user_id), INDEX idx_session_customer (customer_id, started_at), PRIMARY KEY (session_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE refresh_token (token_hash VARCHAR(64) NOT NULL, user_id VARCHAR(36) NOT NULL, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL, INDEX idx_refresh_user (user_id), PRIMARY KEY (token_hash)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE session_results (products JSON DEFAULT NULL, ai_team_profile JSON DEFAULT NULL, diagnostic JSON DEFAULT NULL, extra JSON DEFAULT NULL, session_id VARCHAR(36) NOT NULL, customer_id VARCHAR(16) NOT NULL, questionnaire_id VARCHAR(36) NOT NULL, created_at DATETIME NOT NULL, INDEX idx_results_questionnaire (questionnaire_id), PRIMARY KEY (session_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE shopify_connection (updated_at DATETIME NOT NULL, customer_id VARCHAR(16) NOT NULL, shop VARCHAR(255) NOT NULL, access_token LONGTEXT NOT NULL, refresh_token LONGTEXT DEFAULT NULL, token_expires_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (customer_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE system_prompt_version (version_id VARCHAR(36) NOT NULL, prompt_key VARCHAR(100) NOT NULL, text LONGTEXT NOT NULL, updated_at DATETIME NOT NULL, updated_by VARCHAR(180) NOT NULL, INDEX idx_prompt_key_updated (prompt_key, updated_at), PRIMARY KEY (version_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE usage_counter (id INT AUTO_INCREMENT NOT NULL, used INT NOT NULL, customer_id VARCHAR(16) NOT NULL, period_from VARCHAR(10) NOT NULL, period_to VARCHAR(10) NOT NULL, feature VARCHAR(100) NOT NULL, UNIQUE INDEX uniq_usage_period_feature (customer_id, period_from, feature), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE video (updated_at DATETIME NOT NULL, id VARCHAR(36) NOT NULL, title VARCHAR(200) NOT NULL, description LONGTEXT NOT NULL, url VARCHAR(500) NOT NULL, language VARCHAR(2) NOT NULL, category VARCHAR(100) NOT NULL, video_order INT NOT NULL, duration_minutes INT NOT NULL, created_at DATETIME NOT NULL, INDEX idx_video_language (language, video_order), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE webhook_delivery (status VARCHAR(10) NOT NULL, attempts INT NOT NULL, last_status_code INT DEFAULT NULL, next_attempt_at DATETIME DEFAULT NULL, updated_at DATETIME NOT NULL, id VARCHAR(36) NOT NULL, webhook_id VARCHAR(36) NOT NULL, customer_id VARCHAR(16) NOT NULL, event_type VARCHAR(64) NOT NULL, payload JSON NOT NULL, created_at DATETIME NOT NULL, INDEX idx_delivery_webhook (webhook_id, created_at), INDEX idx_delivery_due (status, next_attempt_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE webhook_subscription (updated_at DATETIME NOT NULL, id VARCHAR(36) NOT NULL, customer_id VARCHAR(16) NOT NULL, url VARCHAR(2048) NOT NULL, event_type VARCHAR(64) NOT NULL, method VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL, INDEX idx_webhook_customer (customer_id, event_type), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id))');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE api_key');
        $this->addSql('DROP TABLE app_setting');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE assignation');
        $this->addSql('DROP TABLE assignation_answer');
        $this->addSql('DROP TABLE customer');
        $this->addSql('DROP TABLE customer_plan');
        $this->addSql('DROP TABLE customer_styles');
        $this->addSql('DROP TABLE diagnostic');
        $this->addSql('DROP TABLE domain_event_log');
        $this->addSql('DROP TABLE feature');
        $this->addSql('DROP TABLE flow');
        $this->addSql('DROP TABLE impersonation_log');
        $this->addSql('DROP TABLE job');
        $this->addSql('DROP TABLE organization');
        $this->addSql('DROP TABLE organization_user');
        $this->addSql('DROP TABLE password_reset_code');
        $this->addSql('DROP TABLE plan');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE project');
        $this->addSql('DROP TABLE prompt');
        $this->addSql('DROP TABLE questionnaire');
        $this->addSql('DROP TABLE questionnaire_dashboard');
        $this->addSql('DROP TABLE questionnaire_session');
        $this->addSql('DROP TABLE refresh_token');
        $this->addSql('DROP TABLE session_results');
        $this->addSql('DROP TABLE shopify_connection');
        $this->addSql('DROP TABLE system_prompt_version');
        $this->addSql('DROP TABLE usage_counter');
        $this->addSql('DROP TABLE video');
        $this->addSql('DROP TABLE webhook_delivery');
        $this->addSql('DROP TABLE webhook_subscription');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
