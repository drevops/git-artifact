<?php

namespace IntegratedExperts\Robo\Tests\Integration;

/**
 * Class TagTest.
 *
 * @group integration
 */
class TagTest extends AbstractIntegrationTest
{

    /**
     * {@inheritdoc}
     */
    public function setUp(): void
    {
        $this->mode = 'force-push';
        parent::setUp();
    }

    public function testDetachedTag()
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
