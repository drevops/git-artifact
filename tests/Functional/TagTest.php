<?php

declare(strict_types = 1);

namespace DrevOps\GitArtifact\Tests\Functional;

/**
 * Class TagTest.
 *
 * @group integration
 *
 * @covers \DrevOps\GitArtifact\GitTrait
 * @covers \DrevOps\GitArtifact\ArtifactTrait
 * @covers \DrevOps\GitArtifact\FilesystemTrait
 */
class TagTest extends AbstractFunctionalTestCase
{

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->mode = 'force-push';
        parent::setUp();
    }

    public function testDetachedTag(): void
    {
        $this->gitCreateFixtureCommits(2);
        $this->gitAddTag($this->src, 'tag1');
        $this->gitCheckout($this->src, 'tag1');
        $srcBranches = $this->runGitCommand('branch');

        $output = $this->assertBuildSuccess();
        $this->assertStringContainsString('Mode:                  force-push', $output);
        $this->assertStringContainsString('Will push:             Yes', $output);

        $this->assertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);
        $this->assertEquals($srcBranches, $this->runGitCommand('branch'), 'Cleanup has correctly returned to the previous branch.');
    }
}
