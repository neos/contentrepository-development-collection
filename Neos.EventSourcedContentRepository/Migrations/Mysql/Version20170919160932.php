<?php

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20170919160932 extends AbstractMigration
{
    /**
     * @return string
     */
    public function getDescription()
    {
        return 'Introduce projection for workspaces';
    }

    /**
     * @param Schema $schema
     *
     * @return void
     */
    public function up(Schema $schema)
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on "mysql".');
        $this->addSql('CREATE TABLE neos_contentrepository_projection_workspace_v1 (persistence_object_identifier VARCHAR(40) NOT NULL, workspacename VARCHAR(255) NOT NULL, baseworkspacename VARCHAR(255) NOT NULL, workspacetitle VARCHAR(255) NOT NULL, workspacedescription VARCHAR(255) NOT NULL, workspaceowner VARCHAR(255) NOT NULL, PRIMARY KEY(persistence_object_identifier)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
    }

    /**
     * @param Schema $schema
     *
     * @return void
     */
    public function down(Schema $schema)
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on "mysql".');
        $this->addSql('DROP TABLE neos_contentrepository_projection_workspace_v1');
    }
}
