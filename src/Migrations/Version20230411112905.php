<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorPayunityBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;

final class Version20230411112905 extends EntityManagerMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX payment_identifier_idx ON mono_connector_payunity_payments (payment_identifier)');
        $this->addSql('CREATE INDEX psp_identifier_idx ON mono_connector_payunity_payments (psp_identifier)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX payment_identifier_idx ON mono_connector_payunity_payments');
        $this->addSql('DROP INDEX psp_identifier_idx ON mono_connector_payunity_payments');
    }
}
