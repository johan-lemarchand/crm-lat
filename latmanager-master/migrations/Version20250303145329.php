<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250303145329 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE Board (id INT IDENTITY NOT NULL, project_id INT NOT NULL, name NVARCHAR(255) NOT NULL, description VARCHAR(MAX), createdAt DATETIME2(6) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_99970443166D1F9C ON Board (project_id)');
        $this->addSql('EXEC sp_addextendedproperty N\'MS_Description\', N\'(DC2Type:datetime_immutable)\', N\'SCHEMA\', \'dbo\', N\'TABLE\', \'Board\', N\'COLUMN\', \'createdAt\'');
        $this->addSql('CREATE TABLE Card (id INT IDENTITY NOT NULL, column_id INT NOT NULL, title NVARCHAR(255) NOT NULL, description VARCHAR(MAX), position INT NOT NULL, createdAt DATETIME2(6), updatedAt DATETIME2(6) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_B62637EDBE8E8ED5 ON Card (column_id)');
        $this->addSql('EXEC sp_addextendedproperty N\'MS_Description\', N\'(DC2Type:datetime_immutable)\', N\'SCHEMA\', \'dbo\', N\'TABLE\', \'Card\', N\'COLUMN\', \'updatedAt\'');
        $this->addSql('CREATE TABLE card_label (card_id INT NOT NULL, label_id INT NOT NULL, PRIMARY KEY (card_id, label_id))');
        $this->addSql('CREATE INDEX IDX_3693A12E4ACC9A20 ON card_label (card_id)');
        $this->addSql('CREATE INDEX IDX_3693A12E33B92F39 ON card_label (label_id)');
        $this->addSql('CREATE TABLE CommandExecution (id INT IDENTITY NOT NULL, command_id INT NOT NULL, startedAt DATETIME2(6) NOT NULL, endedAt DATETIME2(6), status NVARCHAR(255) NOT NULL, output VARCHAR(MAX), error VARCHAR(MAX), duration DOUBLE PRECISION, exitCode NVARCHAR(255), stackTrace VARCHAR(MAX), PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_8042B21533E1689A ON CommandExecution (command_id)');
        $this->addSql('CREATE TABLE Comment (id INT IDENTITY NOT NULL, card_id INT NOT NULL, content VARCHAR(MAX) NOT NULL, createdAt DATETIME2(6) NOT NULL, updatedAt DATETIME2(6), PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_5BC96BF04ACC9A20 ON Comment (card_id)');
        $this->addSql('EXEC sp_addextendedproperty N\'MS_Description\', N\'(DC2Type:datetime_immutable)\', N\'SCHEMA\', \'dbo\', N\'TABLE\', \'Comment\', N\'COLUMN\', \'createdAt\'');
        $this->addSql('EXEC sp_addextendedproperty N\'MS_Description\', N\'(DC2Type:datetime_immutable)\', N\'SCHEMA\', \'dbo\', N\'TABLE\', \'Comment\', N\'COLUMN\', \'updatedAt\'');
        $this->addSql('CREATE TABLE Label (id INT IDENTITY NOT NULL, name NVARCHAR(255) NOT NULL, color NVARCHAR(7) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE LogResume (id INT IDENTITY NOT NULL, command_id INT NOT NULL, executionDate DATETIME2(6) NOT NULL, resume VARCHAR(MAX) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_4FDA50F433E1689A ON LogResume (command_id)');
        $this->addSql('CREATE TABLE PraxedoApiLog (id INT IDENTITY NOT NULL, execution_id INT NOT NULL, requestXml VARCHAR(MAX) NOT NULL, responseXml VARCHAR(MAX) NOT NULL, endpoint NVARCHAR(255) NOT NULL, method NVARCHAR(10) NOT NULL, statusCode INT NOT NULL, createdAt DATETIME2(6) NOT NULL, duration DOUBLE PRECISION, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_B62BC21257125544 ON PraxedoApiLog (execution_id)');
        $this->addSql('CREATE TABLE Project (id INT IDENTITY NOT NULL, name NVARCHAR(255) NOT NULL, description VARCHAR(MAX), createdAt DATETIME2(6) NOT NULL, updatedAt DATETIME2(6) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('EXEC sp_addextendedproperty N\'MS_Description\', N\'(DC2Type:datetime_immutable)\', N\'SCHEMA\', \'dbo\', N\'TABLE\', \'Project\', N\'COLUMN\', \'createdAt\'');
        $this->addSql('EXEC sp_addextendedproperty N\'MS_Description\', N\'(DC2Type:datetime_immutable)\', N\'SCHEMA\', \'dbo\', N\'TABLE\', \'Project\', N\'COLUMN\', \'updatedAt\'');
        $this->addSql('CREATE TABLE WavesoftLog (id INT IDENTITY NOT NULL, execution_id INT NOT NULL, modifiedData VARCHAR(MAX) NOT NULL, tableName NVARCHAR(255) NOT NULL, operation NVARCHAR(50) NOT NULL, createdAt DATETIME2(6) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_81AB37AD57125544 ON WavesoftLog (execution_id)');
        $this->addSql('CREATE TABLE [column] (id INT IDENTITY NOT NULL, board_id INT NOT NULL, name NVARCHAR(255) NOT NULL, position INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_7D53877EE7EC5785 ON [column] (board_id)');
        $this->addSql('CREATE TABLE command (id INT IDENTITY NOT NULL, name NVARCHAR(255), script_name NVARCHAR(255), recurrence NVARCHAR(255), interval INT, attempt_max INT, last_execution_date DATETIME2(6), next_execution_date DATETIME2(6), last_status NVARCHAR(255), start_time DATETIME2(6), end_time DATETIME2(6), status_scheduler BIT, status_send_email BIT, manual_execution_date DATETIME2(6), active BIT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE command ADD CONSTRAINT DF_8ECAEAD4_2C736374 DEFAULT 5 FOR attempt_max');
        $this->addSql('ALTER TABLE command ADD CONSTRAINT DF_8ECAEAD4_4B1EFC02 DEFAULT 1 FOR active');
        $this->addSql('CREATE TABLE odf_execution (id INT IDENTITY NOT NULL, step NVARCHAR(255) NOT NULL, duration DOUBLE PRECISION NOT NULL, createdAt DATETIME2(6) NOT NULL, odfLog_id INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_19745CB4851D7456 ON odf_execution (odfLog_id)');
        $this->addSql('EXEC sp_addextendedproperty N\'MS_Description\', N\'(DC2Type:datetime_immutable)\', N\'SCHEMA\', \'dbo\', N\'TABLE\', \'odf_execution\', N\'COLUMN\', \'createdAt\'');
        $this->addSql('CREATE TABLE odf_log (id INT IDENTITY NOT NULL, name NVARCHAR(255) NOT NULL, status NVARCHAR(50) NOT NULL, executionTime DOUBLE PRECISION NOT NULL, executionTimePause DOUBLE PRECISION, createdAt DATETIME2(6) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('EXEC sp_addextendedproperty N\'MS_Description\', N\'(DC2Type:datetime_immutable)\', N\'SCHEMA\', \'dbo\', N\'TABLE\', \'odf_log\', N\'COLUMN\', \'createdAt\'');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT IDENTITY NOT NULL, body VARCHAR(MAX) NOT NULL, headers VARCHAR(MAX) NOT NULL, queue_name NVARCHAR(190) NOT NULL, created_at DATETIME2(6) NOT NULL, available_at DATETIME2(6) NOT NULL, delivered_at DATETIME2(6), PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
        $this->addSql('EXEC sp_addextendedproperty N\'MS_Description\', N\'(DC2Type:datetime_immutable)\', N\'SCHEMA\', \'dbo\', N\'TABLE\', \'messenger_messages\', N\'COLUMN\', \'created_at\'');
        $this->addSql('EXEC sp_addextendedproperty N\'MS_Description\', N\'(DC2Type:datetime_immutable)\', N\'SCHEMA\', \'dbo\', N\'TABLE\', \'messenger_messages\', N\'COLUMN\', \'available_at\'');
        $this->addSql('EXEC sp_addextendedproperty N\'MS_Description\', N\'(DC2Type:datetime_immutable)\', N\'SCHEMA\', \'dbo\', N\'TABLE\', \'messenger_messages\', N\'COLUMN\', \'delivered_at\'');
        $this->addSql('ALTER TABLE Board ADD CONSTRAINT FK_99970443166D1F9C FOREIGN KEY (project_id) REFERENCES Project (id)');
        $this->addSql('ALTER TABLE Card ADD CONSTRAINT FK_B62637EDBE8E8ED5 FOREIGN KEY (column_id) REFERENCES [column] (id)');
        $this->addSql('ALTER TABLE card_label ADD CONSTRAINT FK_3693A12E4ACC9A20 FOREIGN KEY (card_id) REFERENCES Card (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE card_label ADD CONSTRAINT FK_3693A12E33B92F39 FOREIGN KEY (label_id) REFERENCES Label (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE CommandExecution ADD CONSTRAINT FK_8042B21533E1689A FOREIGN KEY (command_id) REFERENCES command (id)');
        $this->addSql('ALTER TABLE Comment ADD CONSTRAINT FK_5BC96BF04ACC9A20 FOREIGN KEY (card_id) REFERENCES Card (id)');
        $this->addSql('ALTER TABLE LogResume ADD CONSTRAINT FK_4FDA50F433E1689A FOREIGN KEY (command_id) REFERENCES command (id)');
        $this->addSql('ALTER TABLE PraxedoApiLog ADD CONSTRAINT FK_B62BC21257125544 FOREIGN KEY (execution_id) REFERENCES CommandExecution (id)');
        $this->addSql('ALTER TABLE WavesoftLog ADD CONSTRAINT FK_81AB37AD57125544 FOREIGN KEY (execution_id) REFERENCES CommandExecution (id)');
        $this->addSql('ALTER TABLE [column] ADD CONSTRAINT FK_7D53877EE7EC5785 FOREIGN KEY (board_id) REFERENCES Board (id)');
        $this->addSql('ALTER TABLE odf_execution ADD CONSTRAINT FK_19745CB4851D7456 FOREIGN KEY (odfLog_id) REFERENCES odf_log (id)');
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
        $this->addSql('ALTER TABLE Board DROP CONSTRAINT FK_99970443166D1F9C');
        $this->addSql('ALTER TABLE Card DROP CONSTRAINT FK_B62637EDBE8E8ED5');
        $this->addSql('ALTER TABLE card_label DROP CONSTRAINT FK_3693A12E4ACC9A20');
        $this->addSql('ALTER TABLE card_label DROP CONSTRAINT FK_3693A12E33B92F39');
        $this->addSql('ALTER TABLE CommandExecution DROP CONSTRAINT FK_8042B21533E1689A');
        $this->addSql('ALTER TABLE Comment DROP CONSTRAINT FK_5BC96BF04ACC9A20');
        $this->addSql('ALTER TABLE LogResume DROP CONSTRAINT FK_4FDA50F433E1689A');
        $this->addSql('ALTER TABLE PraxedoApiLog DROP CONSTRAINT FK_B62BC21257125544');
        $this->addSql('ALTER TABLE WavesoftLog DROP CONSTRAINT FK_81AB37AD57125544');
        $this->addSql('ALTER TABLE [column] DROP CONSTRAINT FK_7D53877EE7EC5785');
        $this->addSql('ALTER TABLE odf_execution DROP CONSTRAINT FK_19745CB4851D7456');
        $this->addSql('DROP TABLE Board');
        $this->addSql('DROP TABLE Card');
        $this->addSql('DROP TABLE card_label');
        $this->addSql('DROP TABLE CommandExecution');
        $this->addSql('DROP TABLE Comment');
        $this->addSql('DROP TABLE Label');
        $this->addSql('DROP TABLE LogResume');
        $this->addSql('DROP TABLE PraxedoApiLog');
        $this->addSql('DROP TABLE Project');
        $this->addSql('DROP TABLE WavesoftLog');
        $this->addSql('DROP TABLE [column]');
        $this->addSql('DROP TABLE command');
        $this->addSql('DROP TABLE odf_execution');
        $this->addSql('DROP TABLE odf_log');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
