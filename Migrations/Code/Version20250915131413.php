<?php
namespace Neos\Flow\Core\Migrations;

/**
 * Adjusts code for package renaming
 */
class Version20250915131413 extends AbstractMigration
{

    public function getIdentifier()
    {
        return 'Oniva.GraphQL-20250915131413';
    }

    /**
     * @return void
     */
    public function up()
    {
        $this->searchAndReplace('Oniva\GraphQL', 'Oniva\GraphQL');
        $this->searchAndReplace('Oniva.GraphQL', 'Oniva.GraphQL');
        $this->searchAndReplace('Oniva_GraphQL', 'Oniva_GraphQL');
        $this->moveSettingsPaths('t3n.GraphQL', 'Oniva.GraphQL');
    }
}
