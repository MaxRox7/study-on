<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250406132511 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE course (id_course SERIAL NOT NULL, symbol_code VARCHAR(255) NOT NULL, title_course VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, PRIMARY KEY(id_course))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_169E6FB9D723AECA ON course (symbol_code)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE lesson (id_lesson SERIAL NOT NULL, id_course INT NOT NULL, title_lesson VARCHAR(255) NOT NULL, content TEXT NOT NULL, order_number INT NOT NULL, name_lesson VARCHAR(50) NOT NULL, status_lesson VARCHAR(50) NOT NULL, PRIMARY KEY(id_lesson))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_F87474F330A9DA54 ON lesson (id_course)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE lesson ADD CONSTRAINT FK_F87474F330A9DA54 FOREIGN KEY (id_course) REFERENCES course (id_course) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE lesson DROP CONSTRAINT FK_F87474F330A9DA54
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE course
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE lesson
        SQL);
    }
}
