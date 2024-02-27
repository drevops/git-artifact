<?php
/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */

use DrevOps\Robo\ArtifactTrait;
use Robo\Tasks;

/**
 * Class RoboFile.
 */
class RoboFile extends Tasks
{

    use ArtifactTrait {
        ArtifactTrait::__construct as private __artifactConstruct;
    }

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->__artifactConstruct();
    }
}
