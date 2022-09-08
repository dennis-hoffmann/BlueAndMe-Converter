<?php

namespace App;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

/**
 * Command to convert your music for use with Fiat blue&me car radio system.
 *
 * @uses    https://github.com/JamesHeinrich/getID3/
 * Requires FFmpeg https://wiki.ubuntuusers.de/FFmpeg/
 * and
 * mp3gain https://wiki.ubuntuusers.de/MP3Gain/
 */
class ConvertCommand extends Command
{
    /**
     * @var Finder
     */
    protected $finder;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var \getID3
     */
    protected $tagger;

    public function __construct()
    {
        parent::__construct();

        $this->finder = new Finder();
        $this->filesystem = new Filesystem();
        $this->tagger = new \getID3();
    }

    /**
     * Configure command.
     */
    protected function configure()
    {
        $this
            ->setName('convert')
            ->setDescription('Convert audio files for use in Fiat blue&me car radio systems.')
            ->setHelp('You may specify either a source directory or a text file containing absolute paths to audio files')
        ;

        $this->addArgument(
            'source',
            InputArgument::REQUIRED,
            'Source directory or .xspf file. Set "-x" if using .xspf file.'
        );

        $this->addArgument(
            'target',
            InputArgument::REQUIRED,
            'Target directory where converted files will be written.'
        );

        $this->addOption(
            'xspf',
            'x',
            InputOption::VALUE_NONE,
            'Must be set when using a .xspf file'
        );

        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Force convert even when already mp3'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $force = $input->hasOption('force');
        $outputDirectory = $input->getArgument('target');

        if (!is_dir($outputDirectory)) {
            throw new \RuntimeException(sprintf('Output directory "%s" does not exist.'));
        }

        if ($playlistFile = $input->getOption('xspf')) {
            $xml = simplexml_load_file($playlistFile);

            if (!$xml) {
                throw new \RuntimeException(sprintf('Unable to parse XML file "%s".', $playlistFile));
            }

            $tracks = $this->parseXSPF($xml);
        } else {
            $inputDirectory = $input->getArgument('source');

            if (!is_dir($inputDirectory)) {
                throw new \RuntimeException(sprintf('Input directory "%s" does not exist.', $inputDirectory));
            }

            $tracks = $this
                ->finder
                ->ignoreUnreadableDirs()
                ->in($inputDirectory)
                ->name('*.flac')
                ->name('*.oga')
                ->name('*.m4a')
                ->name('*.mp3')
                ->name('*.wma')
                ->name('*.aac')
                ->name('*.wav')
                ->files()
            ;
        }

        if (empty($tracks)) {
            $output->writeln('<error>No tracks found. </error>');
            exit;
        }

        $output->writeln(sprintf('<info>Will work with %d files.</info>', count($tracks)));

        foreach ($tracks as $track) {
            $this->convert($track, $outputDirectory, $output, $force);

            $output->writeln(sprintf('<info>Converted %s.</info>', $track->getFilename()));
        }
    }

    /**
     * Convert an audio file for use with Fiat blue&me car radio system.
     *
     * It will convert audio files to .mp3 using ffmpeg,
     * rename the file like <title> - <album artist> - <album>,
     * strip all tags other than id3v1,
     * and normalize the audio volume.
     *
     * @param \SplFileInfo $file
     * @param string $target
     * @param OutputInterface $output
     * @param bool $force
     *
     * @return bool
     */
    protected function convert(\SplFileInfo $file, string $target, OutputInterface $output, $force = false): bool
    {
        $filename = pathinfo($file->getFilename(), PATHINFO_FILENAME);

        $fileInfo = $this->tagger->analyze($file->getPathname());
        \getid3_lib::CopyTagsToComments($fileInfo);
        $tags = array_column($fileInfo['comments_html'] ?? [], 0);

        if (!empty($tags)) {
            $title = $tags['title'] ?? '';
            $album = $tags['album'] ?? '';
            $artist = $tags['albumartist'] ?? '';

            if ($artist === '') {
                $artist = $tags['artist'] ?? '';
                $tags['albumartist'] = $artist;
            }

            if ($artist && $title && $album) {
                $name = self::normalizeString(sprintf('%s - %s - %s', $title, $artist, $album));

                if ($name != '' && strlen($name) < 200) {
                    $filename = $name;
                }
            }
        }

        $filename = self::normalizeString($filename);
        $outputFile = $target . DIRECTORY_SEPARATOR . $filename . '.mp3';

        $this->filesystem->mkdir($target, 0775);

        if ($force || $file->getExtension() !== 'mp3') {
            $command = sprintf('ffmpeg -i "%s" -ab 320k "%s"', $file->getPathname(), $outputFile);

            $process = Process::fromShellCommandline($command)
                ->setTimeout(160)
            ;

            $process->start();

            foreach ($process as $type => $data) {
                $output->write($data);
            }

            $output->writeln(sprintf('<info>Converted %s</info>', $filename));
        } else {
            $this->filesystem->copy($file->getPathname(), $outputFile);
            $output->writeln(sprintf('<info>Copied %s</info>', $filename));
        }

        if ($this->normalize($outputFile, $output)) {
            $output->writeln(sprintf('<info>Normalized %s</info>', $filename));
        } else {
            $output->writeln(sprintf('<error>Error normalizing %s</error>', $filename));
        }

        if ($this->writeTags($outputFile, $tags)) {
            $output->writeln(sprintf('<info>Wrote tags to %s</info>', $filename));

            return true;
        } else {
            $output->writeln(sprintf('<error>Error writing tags to %s</error>', $filename));

            return false;
        }
    }

    /**
     * Write id3v1 tags to file and strip other tags.
     *
     * @param string $fileName
     * @param array $tags
     *
     * @return bool
     */
    private function writeTags(string $fileName, array $tags = []): bool
    {
        $tagData = [
            'TITLE' => [''],
            'ARTIST' => [''],
            'ALBUM' => [''],
            'YEAR' => [''],
            'COMMENT' => [''],
            'TRACKNUMBER' => [''],
        ];

        if (!empty($tags['title'])) {
            $tagData['TITLE'][0] = self::normalizeString($tags['title']);
        }

        if (!empty($tags['albumartist'])) {
            $tagData['ARTIST'][0] = self::normalizeString($tags['albumartist']);
        }

        if (!empty($tags['album'])) {
            $tagData['ALBUM'][0] = self::normalizeString($tags['album']);
        }

        if (!empty($tags['year'])) {
            $tagData['YEAR'][0] = self::normalizeString($tags['year']);
        }

        if (!empty($tags['comment'])) {
            $tagData['COMMENT'][0] = self::normalizeString($tags['comment']);
        }

        if (!empty($tags['track'])) {
            $tagData['TRACKNUMBER'][0] = self::normalizeString($tags['track']);
        }

        $tagWriter = new \getid3_writetags;
        $tagWriter->filename = $fileName;
        $tagWriter->tagformats = ['id3v1'];
        $tagWriter->overwrite_tags = true;
        $tagWriter->tag_encoding = 'ISO-8859-1';
        $tagWriter->remove_other_tags = true;
        $tagWriter->tag_data = $tagData;

        if ($tagWriter->WriteTags()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Normalize audio track using mp3gain.
     *
     * @param string $targetFile
     * @param OutputInterface $output
     *
     * @return bool
     */
    private function normalize(string $targetFile, OutputInterface $output): bool
    {
        $command = sprintf('mp3gain -p -r -c -s s "%s"', $targetFile);
        $process = Process::fromShellCommandline($command)->setTimeout(60);

        $process->start();

        foreach ($process as $type => $data) {
            $output->write($data);
        }

        return true;
    }

    /**
     * Parse .xspf playlist.
     *
     * @param \SimpleXMLElement $xml
     *
     * @return array
     */
    private function parseXSPF(\SimpleXMLElement $xml): array
    {
        $tracks = [];

        foreach ($xml->trackList->track as $item) {
            $tracks[] = new \SplFileInfo(urldecode(str_replace('file://', '', $item->location)));
        }

        return $tracks;
    }

    /**
     * getID3 library does spooky things with metadata and encodes special characters in it.
     *
     * @param string $str
     *
     * @return string
     */
    private static function normalizeString(string $str = ''): string
    {
        return preg_replace("/[^a-zA-Z0-9\'\-_\(\) ]/", '', html_entity_decode($str));
    }
}
