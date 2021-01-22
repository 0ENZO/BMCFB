<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210122152947 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE record (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, statement_id INT NOT NULL, rate INT NOT NULL, date DATETIME NOT NULL, INDEX IDX_9B349F91A76ED395 (user_id), INDEX IDX_9B349F91849CB65B (statement_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE statement (id INT AUTO_INCREMENT NOT NULL, topic_id INT NOT NULL, content VARCHAR(255) NOT NULL, INDEX IDX_C0DB51761F55203D (topic_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE topic (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE record ADD CONSTRAINT FK_9B349F91A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE record ADD CONSTRAINT FK_9B349F91849CB65B FOREIGN KEY (statement_id) REFERENCES statement (id)');
        $this->addSql('ALTER TABLE statement ADD CONSTRAINT FK_C0DB51761F55203D FOREIGN KEY (topic_id) REFERENCES topic (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE record DROP FOREIGN KEY FK_9B349F91849CB65B');
        $this->addSql('ALTER TABLE statement DROP FOREIGN KEY FK_C0DB51761F55203D');
        $this->addSql('DROP TABLE record');
        $this->addSql('DROP TABLE statement');
        $this->addSql('DROP TABLE topic');
    }
}
