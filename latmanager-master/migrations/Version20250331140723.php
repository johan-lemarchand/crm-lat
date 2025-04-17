<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250331140723 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE WavesoftLog DROP CONSTRAINT FK_81AB37AD57125544');
        $this->addSql('DROP INDEX IDX_81AB37AD57125544 ON WavesoftLog');
        $this->addSql('sp_rename \'WavesoftLog.modifiedData\', \'automateFile\', \'COLUMN\'');
        $this->addSql('sp_rename \'WavesoftLog.execution_id\', \'trsId\', \'COLUMN\'');
        $this->addSql('sp_rename \'WavesoftLog.operation\', \'userName\', \'COLUMN\'');
        $this->addSql('sp_rename \'WavesoftLog.tableName\', \'messageError\', \'COLUMN\'');
        $this->addSql('ALTER TABLE WavesoftLog ADD status NVARCHAR(10) NOT NULL');
        $this->addSql('ALTER TABLE WavesoftLog ADD aboId INT NOT NULL');
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
        $this->addSql('sp_rename \'WavesoftLog.automateFile\', \'modifiedData\', \'COLUMN\'');
        $this->addSql('sp_rename \'WavesoftLog.messageError\', \'tableName\', \'COLUMN\'');
        $this->addSql('sp_rename \'WavesoftLog.userName\', \'operation\', \'COLUMN\'');
        $this->addSql('ALTER TABLE WavesoftLog ADD execution_id INT NOT NULL');
        $this->addSql('ALTER TABLE WavesoftLog DROP COLUMN trsId');
        $this->addSql('ALTER TABLE WavesoftLog DROP COLUMN status');
        $this->addSql('ALTER TABLE WavesoftLog DROP COLUMN aboId');
        $this->addSql('ALTER TABLE WavesoftLog ADD CONSTRAINT FK_81AB37AD57125544 FOREIGN KEY (execution_id) REFERENCES CommandExecution (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE NONCLUSTERED INDEX IDX_81AB37AD57125544 ON WavesoftLog (execution_id)');
    }
}
