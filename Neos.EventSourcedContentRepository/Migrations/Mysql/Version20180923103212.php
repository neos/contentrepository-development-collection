<?php

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Create the Event Store.
 */
class Version20180923103212 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'The Event store';
    }

    public function up(Schema $schema)
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql',
            'Migration can only be executed safely on "mysql".');

        $this->addSql(' CREATE TABLE neos_eventsourcing_eventstore_events (sequencenumber INT AUTO_INCREMENT NOT NULL, stream VARCHAR(255) NOT NULL, version BIGINT UNSIGNED NOT NULL, type VARCHAR(255) NOT NULL, payload LONGTEXT NOT NULL, metadata LONGTEXT NOT NULL, id VARCHAR(255) NOT NULL, correlationidentifier VARCHAR(255) DEFAULT NULL, causationidentifier VARCHAR(255) DEFAULT NULL, recordedat DATETIME NOT NULL, UNIQUE INDEX id_uniq (id), UNIQUE INDEX stream_version_uniq (stream, version), PRIMARY KEY(sequencenumber)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
    }

    public function down(Schema $schema)
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql',
            'Migration can only be executed safely on "mysql".');

        $this->addSql('DROP TABLE neos_eventsourcing_eventstore_events');
    }
}
