<?php
/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */

use DrevOps\Robo\ArtefactTrait;

/**
 * Class RoboFile.
 */
class RoboFile extends \Robo\Tasks
{

    use ArtefactTrait {
        ArtefactTrait::__construct as private __artifactConstruct;
    }

    public function __construct()
    {
        $this->__artifactConstruct();
    }
}
