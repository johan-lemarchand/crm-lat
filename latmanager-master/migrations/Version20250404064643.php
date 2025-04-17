<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250404064643 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE execution_logs (id INT IDENTITY NOT NULL, executionId NVARCHAR(50) NOT NULL, commandName NVARCHAR(100) NOT NULL, startDate NVARCHAR(255) NOT NULL, endDate NVARCHAR(255), duration INT, status NVARCHAR(20) NOT NULL, message VARCHAR(MAX), PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE sync_date (id INT IDENTITY NOT NULL, code NVARCHAR(50) NOT NULL, sync_type NVARCHAR(50) NOT NULL, last_sync_date DATETIME2(6) NOT NULL, updated_at DATETIME2(6) NOT NULL, description NVARCHAR(255), PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F8489B3577153098 ON sync_date (code) WHERE code IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA db_accessadmin');
        $this->addSql('CREATE SCHEMA db_backupoperator');
        $this->addSql('CREATE SCHEMA db_datareader');
        $this->addSql('CREATE SCHEMA db_datawriter');
        $this->addSql('CREATE SCHEMA db_ddladmin');
        $this->addSql('CREATE SCHEMA db_denydatareader');
        $this->addSql('CREATE SCHEMA db_denydatawriter');
        $this->addSql('CREATE SCHEMA db_owner');
        $this->addSql('CREATE SCHEMA db_securityadmin');
        $this->addSql('CREATE SCHEMA dbo');
        $this->addSql('DROP TABLE execution_logs');
        $this->addSql('DROP TABLE sync_date');
    }
}
