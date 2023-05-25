<?php

namespace SailCMS\Types;

use SailCMS\Locale;

class UploadFile
{
    public readonly string $extension;
    public readonly bool $valid;
    public readonly string $errorMessage;

    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $tmpName,
        public readonly int $error,
        public readonly int $size
    ) {
        $info = pathinfo($this->name);
        $this->extension = $info['extension'];

        $maxSize = setting('assets.maxUploadSize', 5);
        $sizeInBytes = ($maxSize * 1024) * 1024;

        $flag = 0;
        $blackList = setting('assets.extensionBlackList', []);

        if ($blackList->has($this->extension)) {
            $flag = 1;
        }

        // Make sure size limit is ok, no errors and not an executable
        if ($this->size <= $sizeInBytes && $flag === 0 && $this->error === UPLOAD_ERR_OK && !is_executable($this->tmpName)) {
            $this->valid = true;
            $this->errorMessage = '';
        } else {
            $this->valid = false;
            $tooBig = ($this->size > $sizeInBytes);
            $this->errorMessage = $this->parseError($this->error, $tooBig, $flag);
        }
    }

    /**
     *
     * Calculate filesize in a human readable format
     *
     * @return string
     *
     */
    public function size(): string
    {
        if (Locale::current() === 'fr') {
            $units = ['o', 'Ko', 'Mo', 'Go', 'To', 'Po', 'Eo', 'Zo', 'Yo'];
        } else {
            $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        }

        $power = $this->size > 0 ? floor(log($this->size, 1024)) : 0;
        return number_format($this->size / (1024 ** $power), 2, '.', ',') . ' ' . $units[$power];
    }

    /**
     *
     * Move file to final location (only if valid)
     *
     * @param  string  $to
     * @return bool
     *
     */
    public function move(string $to): bool
    {
        if ($this->valid) {
            return move_uploaded_file($this->tmpName, $to);
        }

        return false;
    }

    /**
     *
     * Parse error code to string that can be understood
     *
     * @param  int   $error
     * @param  bool  $tooBig
     * @param  int   $flag
     * @return string
     *
     */
    private function parseError(int $error, bool $tooBig = false, int $flag = 0): string
    {
        // SailCMS limit
        if ($tooBig) {
            return 'file_too_big';
        }

        if ($flag === 1) {
            return 'extension_blacklist';
        }

        // PHP errors
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'file_too_big',
            UPLOAD_ERR_PARTIAL => 'file_partially_uploaded',
            UPLOAD_ERR_NO_FILE => 'file_not_uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'no_temp_directory',
            UPLOAD_ERR_CANT_WRITE => 'permission_denied',
            UPLOAD_ERR_EXTENSION => 'extension_error'
        };
    }
}