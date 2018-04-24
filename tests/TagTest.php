<?php

namespace IntegratedExperts\Robo\Tests;

/**
 * Class TagTest.
 */
class TagTest extends AbstractTest
{

    /**
     * {@inheritdoc}
     */
    public function setUp()
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
        $this->assertContains('Mode:                  force-push', $output);
        $this->assertContains('Will push:             Yes', $output);

        $this->assertFixtureCommits(2, $this->dst, 'testbranch', ['Deployment commit']);
        $this->assertEquals($srcBranches, $this->runGitCommand('branch'), 'Cleanup has correctly returned to the previous branch.');
    }
}
