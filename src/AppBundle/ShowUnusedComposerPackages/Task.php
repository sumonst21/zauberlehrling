<?php

namespace AppBundle\ShowUnusedComposerPackages;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\BufferIO;
use Composer\Package\PackageInterface;

/**
 * Get unused composer packages.
 *
 * The idea is that packages are required either directly from the root package or indirectly. We call these packages
 * n-th degree requirements, where n is the number of links between the package in question and the root package.
 * E.g. a 2nd degree requirement is a package that is required by a package that in turn is directly required by the
 * root package.
 *
 * Deleting a requirement of 2nd or higher degree alone makes no sense, as it will still be required by a first degree
 * requirement and therefore be installed. Hence we concentrate on the first level requirements only.
 */
final class Task
{
    /**
     * @var string
     */
    private $pathToVendor;

    /**
     * @param string $pathToComposerJson
     * @param string|null $pathToVendor
     * @param string $usedFiles one used file per line
     * @return PackageInterface[]
     */
    public function getUnusedComposerPackages($pathToComposerJson, $pathToVendor, $usedFiles)
    {
        $unusedPackages = [];
        $composer = Factory::create(new BufferIO(), $pathToComposerJson);

        $pathToVendor = $pathToVendor ?: $this->getDefaultPathToVendor($pathToComposerJson);
        $this->pathToVendor = $this->assertPathToVendorIsValid($pathToVendor);

        foreach ($composer->getPackage()->getRequires() as $link) {
            $package = $composer->getLocker()->getLockedRepository()->findPackage($link->getTarget(), $link->getConstraint());
            if ($package === null) {
                continue;
            }

            $pathToPackageInstallation = realpath($this->getInstallPath($composer, $package));
            if (strpos($usedFiles, $pathToPackageInstallation) !== false) {
                continue;
            }

            $unusedPackages[] = $package;
        }

        return $unusedPackages;
    }

    /**
     * @param string $pathToComposerJson
     * @return string
     */
    private function getDefaultPathToVendor($pathToComposerJson)
    {
        $projectRoot = realpath(dirname($pathToComposerJson));
        $defaultPathToVendor = $projectRoot . '/vendor';

        return $defaultPathToVendor;
    }

    /**
     * @param string $path
     * @return string path to a readable directory with a trailing slash
     */
    private function assertPathToVendorIsValid($path)
    {
        if (is_dir($path) === false) {
            $message = 'The path "' . $path . '" is no valid directory.';
        } elseif (is_readable($path) === false) {
            $message ='The directory "' . $path . '" is not readable.';
        }

        if (isset($message)) {
            $message .= ' Please specify a readable directory with the ' . Command::OPTION_VENDOR_DIRECTORY . ' '
                      . 'option.';
            throw new \InvalidArgumentException($message);
        }

        $pathWithTrailingDirectorySeparator = rtrim($path, '/') . '/';

        return $pathWithTrailingDirectorySeparator;
    }

    /**
     * @param Composer $composer
     * @param PackageInterface $package
     * @return string
     */
    private function getInstallPath(Composer $composer, PackageInterface $package)
    {
        $pathToVendorInZauberlehrling = $composer->getConfig()->get('vendor-dir');

        $pathToPackageInstallationInZauberlehrling = $composer->getInstallationManager()->getInstallPath($package);
        $pathToPackageInstallationInProject = str_replace($pathToVendorInZauberlehrling, $this->pathToVendor, $pathToPackageInstallationInZauberlehrling);
        $pathToPackageInstallation = realpath($pathToPackageInstallationInProject);

        return $pathToPackageInstallation;
    }
}
