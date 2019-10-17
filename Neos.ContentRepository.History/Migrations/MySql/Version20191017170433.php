<?php
declare(strict_types=1);

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * The migration for providing the history projection storage
 */
class Version20191017170433 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The migration for providing the history projection storage';
    }

    public function up(Schema $schema)
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql',
            'Migration can only be executed safely on "mysql".');

        $this->addSql('CREATE TABLE neos_contentrepository_history_entry(identifier VARCHAR(40) NOT NULL, nodeaggregateidentifier VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, agentidentifier VARCHAR(255) NOT NULL, recordedat DATETIME NOT NULL, payload LONGTEXT NOT NULL, INDEX nodeaggregateidentifier (nodeaggregateidentifier), INDEX recordedat(recordedat), PRIMARY KEY(identifier)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema)
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql',
            'Migration can only be executed safely on "mysql".');

        $this->addSql('DROP TABLE neos_contentrepository_history_entry');
    }
}
