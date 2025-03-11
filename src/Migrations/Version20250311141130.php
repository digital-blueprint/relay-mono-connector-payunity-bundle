<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

final class Version20250311141130 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mono_connector_payunity_payments ADD psp_contract VARCHAR(255) DEFAULT NULL, ADD psp_method VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mono_connector_payunity_payments DROP psp_contract, DROP psp_method');
    }
}
