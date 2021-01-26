<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210126142943 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE profile DROP FOREIGN KEY FK_8157AA0F591CC992');
        $this->addSql('ALTER TABLE topic DROP FOREIGN KEY FK_9D40DE1B591CC992');
        $this->addSql('CREATE TABLE questionnaire (id INT AUTO_INCREMENT NOT NULL, leader_id INT NOT NULL, name VARCHAR(255) NOT NULL, summary LONGTEXT NOT NULL, slug VARCHAR(255) DEFAULT NULL, is_open TINYINT(1) NOT NULL, logo_name VARCHAR(255) DEFAULT NULL, INDEX IDX_7A64DAF73154ED4 (leader_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE questionnaire ADD CONSTRAINT FK_7A64DAF73154ED4 FOREIGN KEY (leader_id) REFERENCES user (id)');
        $this->addSql('DROP TABLE course');
        $this->addSql('DROP INDEX IDX_8157AA0F591CC992 ON profile');
        $this->addSql('ALTER TABLE profile CHANGE course_id questionnaire_id INT NOT NULL');
        $this->addSql('ALTER TABLE profile ADD CONSTRAINT FK_8157AA0FCE07E8FF FOREIGN KEY (questionnaire_id) REFERENCES questionnaire (id)');
        $this->addSql('CREATE INDEX IDX_8157AA0FCE07E8FF ON profile (questionnaire_id)');
        $this->addSql('DROP INDEX IDX_9D40DE1B591CC992 ON topic');
        $this->addSql('ALTER TABLE topic CHANGE course_id questionnaire_id INT NOT NULL');
        $this->addSql('ALTER TABLE topic ADD CONSTRAINT FK_9D40DE1BCE07E8FF FOREIGN KEY (questionnaire_id) REFERENCES questionnaire (id)');
        $this->addSql('CREATE INDEX IDX_9D40DE1BCE07E8FF ON topic (questionnaire_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE profile DROP FOREIGN KEY FK_8157AA0FCE07E8FF');
        $this->addSql('ALTER TABLE topic DROP FOREIGN KEY FK_9D40DE1BCE07E8FF');
        $this->addSql('CREATE TABLE course (id INT AUTO_INCREMENT NOT NULL, leader_id INT NOT NULL, name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, summary VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, slug VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, is_open TINYINT(1) NOT NULL, logo_name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, INDEX IDX_169E6FB973154ED4 (leader_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE course ADD CONSTRAINT FK_169E6FB973154ED4 FOREIGN KEY (leader_id) REFERENCES user (id)');
        $this->addSql('DROP TABLE questionnaire');
        $this->addSql('DROP INDEX IDX_8157AA0FCE07E8FF ON profile');
        $this->addSql('ALTER TABLE profile CHANGE questionnaire_id course_id INT NOT NULL');
        $this->addSql('ALTER TABLE profile ADD CONSTRAINT FK_8157AA0F591CC992 FOREIGN KEY (course_id) REFERENCES course (id)');
        $this->addSql('CREATE INDEX IDX_8157AA0F591CC992 ON profile (course_id)');
        $this->addSql('DROP INDEX IDX_9D40DE1BCE07E8FF ON topic');
        $this->addSql('ALTER TABLE topic CHANGE questionnaire_id course_id INT NOT NULL');
        $this->addSql('ALTER TABLE topic ADD CONSTRAINT FK_9D40DE1B591CC992 FOREIGN KEY (course_id) REFERENCES course (id)');
        $this->addSql('CREATE INDEX IDX_9D40DE1B591CC992 ON topic (course_id)');
    }
}
