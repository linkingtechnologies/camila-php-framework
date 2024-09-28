<?php

namespace lebedevsergey\ODT2XHTML\Helpers;

use lebedevsergey\ODT2XHTML\Exceptions\ODT2XHTMLException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use SplFileObject;
use ZipArchive;

class FilesHelper
{
    static public function unzipFile($fileName, $pathToExtract)
    {
        $archive = new ZipArchive();
        if ($archive->open($fileName) !== TRUE) {
            throw new ODT2XHTMLException("Cannot open Zip-file $fileName");
        }
        $archive->extractTo('');
        if (!$archive->extractTo($pathToExtract)) {
            throw new ODT2XHTMLException("Cannot extract Zip-file to folder $pathToExtract");
        }
        $archive->close();
    }

    static public function doesFileExist($fileName)
    {
        $file = new SplFileInfo($fileName);
        return $file && $file->isFile() && $file->isReadable() && $file->getOwner() === fileowner(__FILE__);
    }

    static public function getFileMIMEInfo($file, $mode)
    {
        if (empty($mode) || is_string($mode)) {
            return null;
        }

        $mimeType = '';
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if (!empty($finfo)) {
            $mimeType = finfo_file($finfo, $file);
            finfo_close($finfo);
        }

        return $mimeType;
    }

    /**
     * first checks if dir exists
     * and then creates it doesn't
     * @param $dir
     */
    static public function createDir($dir)
    {
        if (!file_exists($dir) || (file_exists($dir) && !is_dir($dir))) {
            mkdir($dir, 0777, true);
        }
    }

    /**
     * removes dir recursively
     * taken from https://stackoverflow.com/questions/3349753/delete-directory-with-files-in-it
     * @param $dir
     */
    static public function deleteDirRecursive($dir)
    {
        if (!file_exists($dir)) {
            return;
        }

        $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($dir);
    }

    static public function writeToFile($fileName, $mode = '', $buffer = '')
    {
        $file = new SplFileInfo($fileName);
        if ($file->isFile() && $file->isReadable() && $file->getOwner() === fileowner(__FILE__)) {
            $file = new SplFileObject($fileName);
        }
        $handle = $file->openFile($mode);
        $handle->fwrite($buffer);
    }

}