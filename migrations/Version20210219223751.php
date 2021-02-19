<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210219223751 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE profile (id INT AUTO_INCREMENT NOT NULL, questionnaire_id INT NOT NULL, title VARCHAR(255) NOT NULL, content LONGTEXT DEFAULT NULL, INDEX IDX_8157AA0FCE07E8FF (questionnaire_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE questionnaire (id INT AUTO_INCREMENT NOT NULL, leader_id INT NOT NULL, name VARCHAR(255) NOT NULL, summary LONGTEXT NOT NULL, slug VARCHAR(255) DEFAULT NULL, is_open TINYINT(1) NOT NULL, logo_name VARCHAR(255) DEFAULT NULL, INDEX IDX_7A64DAF73154ED4 (leader_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE record (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, statement_id INT NOT NULL, rate INT NOT NULL, date DATETIME NOT NULL, INDEX IDX_9B349F91A76ED395 (user_id), INDEX IDX_9B349F91849CB65B (statement_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE statement (id INT AUTO_INCREMENT NOT NULL, topic_id INT NOT NULL, profile_id INT NOT NULL, content VARCHAR(255) NOT NULL, INDEX IDX_C0DB51761F55203D (topic_id), INDEX IDX_C0DB5176CCFA12B8 (profile_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE topic (id INT AUTO_INCREMENT NOT NULL, questionnaire_id INT NOT NULL, title VARCHAR(255) NOT NULL, INDEX IDX_9D40DE1BCE07E8FF (questionnaire_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE track (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, action VARCHAR(255) NOT NULL, date DATETIME NOT NULL, INDEX IDX_D6E3F8A6A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(255) DEFAULT NULL, last_name VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE profile ADD CONSTRAINT FK_8157AA0FCE07E8FF FOREIGN KEY (questionnaire_id) REFERENCES questionnaire (id)');
        $this->addSql('ALTER TABLE questionnaire ADD CONSTRAINT FK_7A64DAF73154ED4 FOREIGN KEY (leader_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE record ADD CONSTRAINT FK_9B349F91A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE record ADD CONSTRAINT FK_9B349F91849CB65B FOREIGN KEY (statement_id) REFERENCES statement (id)');
        $this->addSql('ALTER TABLE statement ADD CONSTRAINT FK_C0DB51761F55203D FOREIGN KEY (topic_id) REFERENCES topic (id)');
        $this->addSql('ALTER TABLE statement ADD CONSTRAINT FK_C0DB5176CCFA12B8 FOREIGN KEY (profile_id) REFERENCES profile (id)');
        $this->addSql('ALTER TABLE topic ADD CONSTRAINT FK_9D40DE1BCE07E8FF FOREIGN KEY (questionnaire_id) REFERENCES questionnaire (id)');
        $this->addSql('ALTER TABLE track ADD CONSTRAINT FK_D6E3F8A6A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE statement DROP FOREIGN KEY FK_C0DB5176CCFA12B8');
        $this->addSql('ALTER TABLE profile DROP FOREIGN KEY FK_8157AA0FCE07E8FF');
        $this->addSql('ALTER TABLE topic DROP FOREIGN KEY FK_9D40DE1BCE07E8FF');
        $this->addSql('ALTER TABLE record DROP FOREIGN KEY FK_9B349F91849CB65B');
        $this->addSql('ALTER TABLE statement DROP FOREIGN KEY FK_C0DB51761F55203D');
        $this->addSql('ALTER TABLE questionnaire DROP FOREIGN KEY FK_7A64DAF73154ED4');
        $this->addSql('ALTER TABLE record DROP FOREIGN KEY FK_9B349F91A76ED395');
        $this->addSql('ALTER TABLE track DROP FOREIGN KEY FK_D6E3F8A6A76ED395');
        $this->addSql('DROP TABLE profile');
        $this->addSql('DROP TABLE questionnaire');
        $this->addSql('DROP TABLE record');
        $this->addSql('DROP TABLE statement');
        $this->addSql('DROP TABLE topic');
        $this->addSql('DROP TABLE track');
        $this->addSql('DROP TABLE user');
    }
}
