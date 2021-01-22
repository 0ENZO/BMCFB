<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210122170259 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE statement ADD profile_id INT NOT NULL');
        $this->addSql('ALTER TABLE statement ADD CONSTRAINT FK_C0DB5176CCFA12B8 FOREIGN KEY (profile_id) REFERENCES profile (id)');
        $this->addSql('CREATE INDEX IDX_C0DB5176CCFA12B8 ON statement (profile_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE statement DROP FOREIGN KEY FK_C0DB5176CCFA12B8');
        $this->addSql('DROP INDEX IDX_C0DB5176CCFA12B8 ON statement');
        $this->addSql('ALTER TABLE statement DROP profile_id');
    }
}
