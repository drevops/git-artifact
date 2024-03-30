<?php

declare(strict_types=1);

namespace DrevOps\GitArtifact\Tests\Traits;

use CzProject\GitPhp\GitException;
use DrevOps\GitArtifact\Git\ArtifactGit;
use DrevOps\GitArtifact\Git\ArtifactGitRepository;
use DrevOps\GitArtifact\Tests\Exception\ErrorException;
use PHPUnit\Framework\AssertionFailedError;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Trait CommandTrait.
 */
trait CommandTrait {

  /**
   * Fixture source repository directory.
   *
   * @var string
   */
  protected $src;

  /**
   * Fixture remote repository directory.
   *
   * @var string
   */
  protected $dst;

  /**
   * File system.
   *
   * @var \Symfony\Component\Filesystem\Filesystem
   */
  protected $fs;

  /**
   * Artifact git.
   */
  protected ArtifactGit $git;

  /**
   * Setup test.
   *
   * To be called by test's setUp() method.
   *
   * @param string $src
   *   Source path.
   * @param string $remote
   *   Remote path.
   */
  protected function setUp(string $src, string $remote): void {
    $this->fs = new Filesystem();
    $this->src = $src;
    $this->gitInitRepo($this->src);
    $this->dst = $remote;
    $remoteRepo = $this->gitInitRepo($this->dst);
    // Allow pushing into already checked out branch. We need this to
    // avoid additional management of fixture repository.
    $remoteRepo->setConfigReceiveDenyCurrentBranchIgnore();
  }

  /**
   * Tear down test.
   *
   * To be called by test's tearDown() method.
   */
  protected function tearDown(): void {
    if ($this->fs->exists($this->src)) {
      $this->fs->remove($this->src);
    }
    if ($this->fs->exists($this->dst)) {
      $this->fs->remove($this->dst);
    }
  }

  /**
   * Init git repository.
   *
   * @param string $path
   *   Path to the repository directory.
   */
  protected function gitInitRepo(string $path): ArtifactGitRepository {
    if ($this->fs->exists($path)) {
      $this->fs->remove($path);
    }
    $this->fs->mkdir($path);
    /** @var \DrevOps\GitArtifact\Git\ArtifactGitRepository $repo */
    $repo = $this->git->init($path, ['-b' => 'master']);

    return $repo;
  }

  /**
   * Get all commit hashes in the repository.
   *
   * @param string $path
   *   Path to the repository directory.
   * @param string $format
   *   Format of commits.
   *
   * @return array<string>
   *   Array of commit hashes, sorted from the earliest to the latest commit.
   *
   * @throws \Exception
   */
  protected function gitGetAllCommits(string $path, string $format = '%s'): array {
    $commits = [];
    try {
      $commits = $this->git->open($path)->getCommits($format);
    }
    catch (\Exception $exception) {
      $output = ($exception->getPrevious() instanceof \Throwable) ? $exception->getPrevious()->getMessage() : '';
      $output = trim($output);
      // Different versions of Git may produce these expected messages.
      $expectedErrorMessages = [
        "fatal: bad default revision 'HEAD'",
        "fatal: your current branch 'master' does not have any commits yet",
      ];
      if (!in_array($output, $expectedErrorMessages)) {
        throw $exception;
      }
    }

    return array_reverse(array_filter($commits));
  }

  /**
   * Get a range of commits.
   *
   * @param array<int> $range
   *   Array of commit indexes, stating from 1.
   * @param string $path
   *   Path to the repository directory.
   *
   * @return array<string>
   *   Array of commit hashes, ordered by keys in the $range.
   *
   * @throws \Exception
   */
  protected function gitGetCommitsHashesFromRange(array $range, string $path): array {
    $commits = $this->gitGetAllCommits($path);

    array_walk($range, static function (&$v) : void {
        --$v;
    });

    $ret = [];
    foreach ($range as $key) {
      $ret[] = $commits[$key];
    }

    return $ret;
  }

  /**
   * Get all committed files.
   *
   * @param string $path
   *   Path to the repository directory.
   *
   * @return array<string>
   *   Array of commit committed files.
   */
  protected function gitGetCommittedFiles(string $path): array {
    return $this
      ->git
      ->open($path)
      ->listCommittedFiles();
  }

  /**
   * Create multiple fixture commits.
   *
   * @param int $count
   *   Number of commits to create.
   * @param int $offset
   *   Number of commit indices to offset.
   * @param string|null $path
   *   Optional path to the repository directory. If not provided, fixture
   *   directory is used.
   */
  protected function gitCreateFixtureCommits(int $count, int $offset = 0, string $path = NULL): void {
    $path = $path ? $path : $this->src;
    for ($i = $offset; $i < $count + $offset; $i++) {
      $this->gitCreateFixtureCommit($i + 1, $path);
    }
  }

  /**
   * Create fixture commit with specified index.
   *
   * @param int $index
   *   Index of the commit to be used in the message.
   * @param string|null $path
   *   Optional path to the repository directory. If not provided, fixture
   *   directory is used.
   *
   * @return string
   *   Hash of created commit.
   */
  protected function gitCreateFixtureCommit(int $index, string $path = NULL): string {
    $path = $path ? $path : $this->src;
    $filename = 'f' . $index;
    $this->gitCreateFixtureFile($path, $filename);
    $repo = $this->git->open($path);
    $repo->addFile($filename);
    $message = 'Commit number ' . $index;
    $repo->commitAllChanges($message);
    $lastCommit = $repo->getLastCommit();

    return $lastCommit->getId()->toString();
  }

  /**
   * Commit all uncommitted files.
   *
   * @param string $path
   *   Path to repository.
   * @param string $message
   *   Commit message.
   */
  protected function gitCommitAll(string $path, string $message): void {
    $repo = $this->git->open($path);
    $repo->commitAllChanges($message);
  }

  /**
   * Checkout branch.
   *
   * @param string $path
   *   Path to repository.
   * @param string $branch
   *   Branch name.
   */
  protected function gitCheckout(string $path, string $branch): void {
    try {
      $repo = $this->git->open($path);
      $repo->checkout($branch);
    }
    catch (GitException $gitException) {
      $allowedFails = [
        sprintf("error: pathspec '%s' did not match any file(s) known to git", $branch),
      ];

      if ($gitException->getRunnerResult()) {
        $output = $gitException->getRunnerResult()->getErrorOutput();
      }

      // Re-throw exception if it is not one of the allowed ones.
      if (!isset($output) || empty(array_intersect($output, $allowedFails))) {
        throw $gitException;
      }
    }
  }

  /**
   * Reset git repo at path.
   *
   * @param string $path
   *   Path to the repo.
   */
  protected function gitReset($path): void {
    $repo = $this->git->open($path);
    $repo->resetHard();
    $repo->cleanForce();
  }

  /**
   * Create fixture file at provided path.
   *
   * @param string $path
   *   File path.
   * @param string $name
   *   Optional file name.
   * @param string|array<string> $content
   *   Optional file content.
   *
   * @return string
   *   Created file name.
   */
  protected function gitCreateFixtureFile(string $path, string $name = '', $content = ''): string {
    $name = $name !== '' && $name !== '0' ? $name : 'tmp' . rand(1000, 100000);
    $path = $path . DIRECTORY_SEPARATOR . $name;
    $dir = dirname($path);
    if (!empty($dir)) {
      $this->fs->mkdir($dir);
    }
    $this->fs->touch($path);
    if (!empty($content)) {
      $content = is_array($content) ? implode(PHP_EOL, $content) : $content;
      $this->fs->dumpFile($path, $content);
    }

    return $path;
  }

  /**
   * Remove fixture file at provided path.
   *
   * @param string $path
   *   File path.
   * @param string $name
   *   File name.
   */
  protected function gitRemoveFixtureFile(string $path, string $name): void {
    $path = $path . DIRECTORY_SEPARATOR . $name;
    $this->fs->remove($path);
  }

  /**
   * Create fixture tag with specified name and optional annotation.
   *
   * Annotated tags and lightweight tags have a different object
   * representation in git, therefore may need to be created explicitly for
   * some tests.
   *
   * @param string $path
   *   Optional path to the repository directory.
   * @param string $name
   *   Tag name.
   * @param bool $annotate
   *   Optional flag to add random annotation to the tag. Defaults to FALSE.
   */
  protected function gitAddTag(string $path, string $name, bool $annotate = FALSE): void {
    $repo = $this->git->open($path);
    if ($annotate) {
      $message = 'Annotation for tag ' . $name;
      $repo->createAnnotatedTag($name, $message);
    }
    else {
      $repo->createLightweightTag($name);
    }
  }

  /**
   * Assert that files exist in repository in specified branch.
   *
   * @param string $path
   *   Repository location.
   * @param array<string>|string $files
   *   File or array of files.
   * @param string|null $branch
   *   Optional branch. If set, will be checked out before assertion.
   *
   * @todo Update arguments order and add assertion message.
   */
  protected function gitAssertFilesExist(string $path, $files, string $branch = NULL): void {
    $files = is_array($files) ? $files : [$files];
    if ($branch) {
      $this->gitCheckout($path, $branch);
    }
    foreach ($files as $file) {
      $this->assertFileExists($path . DIRECTORY_SEPARATOR . $file);
    }
  }

  /**
   * Assert that files do not exist in repository in specified branch.
   *
   * @param string $path
   *   Repository location.
   * @param array<string>|string $files
   *   File or array of files.
   * @param string|null $branch
   *   Optional branch. If set, will be checked out before assertion.
   */
  protected function gitAssertFilesNotExist(string $path, $files, string $branch = NULL): void {
    $files = is_array($files) ? $files : [$files];
    if ($branch) {
      $this->gitCheckout($path, $branch);
    }
    foreach ($files as $file) {
      $this->assertFileDoesNotExist($path . DIRECTORY_SEPARATOR . $file);
    }
  }

  /**
   * Assert git files are present and were committed.
   *
   * @param string $path
   *   Path to repo.
   * @param array<string>|string $expectedFiles
   *   Array of files or a single file.
   * @param string $branch
   *   Optional branch name.
   */
  protected function gitAssertFilesCommitted(string $path, $expectedFiles, string $branch = ''): void {
    if ($branch !== '' && $branch !== '0') {
      $this->gitCheckout($path, $branch);
    }
    $expectedFiles = is_array($expectedFiles) ? $expectedFiles : [$expectedFiles];
    $committedFiles = $this->gitGetCommittedFiles($path);
    $this->assertArraySimilar($expectedFiles, $committedFiles);
  }

  /**
   * Assert git files were not committed.
   *
   * @param string $path
   *   Path to repo.
   * @param array<string>|string $expectedFiles
   *   Array of files or a single file.
   * @param string $branch
   *   Optional branch name.
   */
  protected function gitAssertNoFilesCommitted(string $path, $expectedFiles, string $branch = ''): void {
    if ($branch !== '' && $branch !== '0') {
      $this->gitCheckout($path, $branch);
    }
    $expectedFiles = is_array($expectedFiles) ? $expectedFiles : [$expectedFiles];
    $committedFiles = $this->gitGetCommittedFiles($path);
    $intersectedFiles = array_intersect($committedFiles, $expectedFiles);
    $this->assertArraySimilar([], $intersectedFiles);
  }

  /**
   * Assert which git commits are present.
   *
   * @param int $count
   *   Number of commits.
   * @param string $path
   *   Path to the repo.
   * @param string $branch
   *   Branch name.
   * @param array<string> $additionalCommits
   *   Array of additional commits.
   * @param bool $assertFiles
   *   Assert files or not.
   *
   * @throws \Exception
   */
  protected function assertFixtureCommits(int $count, string $path, string $branch, array $additionalCommits = [], bool $assertFiles = TRUE): void {
    $this->gitCheckout($path, $branch);
    $this->gitReset($path);

    $expectedCommits = [];
    $expectedFiles = [];
    for ($i = 1; $i <= $count; $i++) {
      $expectedCommits[] = sprintf('Commit number %s', $i);
      $expectedFiles[] = sprintf('f%s', $i);
    }
    $expectedCommits = array_merge($expectedCommits, $additionalCommits);

    $commits = $this->gitGetAllCommits($path);
    $this->assertEquals($expectedCommits, $commits, 'All fixture commits are present');

    if ($assertFiles) {
      $this->gitAssertFilesExist($this->dst, $expectedFiles, $branch);
    }
  }

  /**
   * Run command.
   *
   * @param string $argsAndOptions
   *   Args and options.
   * @param bool $expectFail
   *   Flag to state that the command should fail.
   * @param string $gitArtifactBin
   *   Git artifact bin.
   *
   * @return array<string>
   *   Array of output lines.
   */
  public function runGitArtifactCommand(string $argsAndOptions, bool $expectFail = FALSE, string $gitArtifactBin = './git-artifact'): array {
    if (!file_exists($gitArtifactBin)) {
      throw new \RuntimeException(sprintf('git-artifact binary is not available at path "%s"', $gitArtifactBin));
    }

    try {
      $output = $this->runCliCommand($gitArtifactBin . ' ' . $argsAndOptions);
      if ($expectFail) {
        throw new AssertionFailedError('Command exited successfully but should not');
      }
    }
    catch (ErrorException $errorException) {
      if (!$expectFail) {
        throw $errorException;
      }
      $output = explode(PHP_EOL, ($errorException->getPrevious() instanceof \Throwable) ? $errorException->getPrevious()->getMessage() : '');
    }

    return $output;
  }

  /**
   * Run CLI command.
   *
   * @param string $command
   *   Command string to run.
   *
   * @return array<string>
   *   Array of output lines.
   *
   * @throws \DrevOps\GitArtifact\Tests\Exception\ErrorException
   *   If commands exists with non-zero status.
   */
  protected function runCliCommand(string $command): array {
    exec($command . ' 2>&1', $output, $code);

    if ($code !== 0) {
      throw new ErrorException(sprintf('Command "%s" exited with non-zero status', $command), $code, '', -1, new ErrorException(implode(PHP_EOL, $output), $code, '', -1));
    }

    return $output;
  }

  /**
   * Asserts that two associative arrays are similar.
   *
   * Both arrays must have the same indexes with identical values
   * without respect to key ordering.
   *
   * @param array $expected
   *   Expected assert.
   * @param array $array
   *   The array want to assert.
   *
   * @phpstan-ignore-next-line
   */
  protected function assertArraySimilar(array $expected, array $array): void {
    $this->assertEquals([], array_diff($array, $expected));
    $this->assertEquals([], array_diff_key($array, $expected));
    foreach ($expected as $key => $value) {
      if (is_array($value)) {
        $this->assertArraySimilar($value, $array[$key]);
      }
      else {
        $this->assertContains($value, $array);
      }
    }
  }

}
