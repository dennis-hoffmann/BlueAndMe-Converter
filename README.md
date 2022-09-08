# Blue and Me Converter

Symfony command to convert your audio files for use with Fiat blue and me car radio systems.  
It will do the following things:
* Convert audio files to mp3 
* Rename the file like `<title> - <album artist> - <album>`
* Strip all tags other than id3v1,
* Normalize the audio volume.

## Requirements
* [PHP >=7.2](https://www.php.net/manual/install.php)
* [Composer](https://getcomposer.org/download)
* [FFmpeg](https://ffmpeg.org/download.html)
* [mp3Gain](http://mp3gain.sourceforge.net/download.php)

## Install
1. Clone or download zip  
3. Go to the location where you have this project on your PC and simply run `composer install`

## Usage
You may convert complete folders or specify your files in an .xspf playlist file.  
You can create these playlist files for example by using VLC Media Player.  

When using an .xspf playlist file simply append `-x` as option.  
To force converting even when the file is already mp3 append `-f`
```sh
./app convert <source> <target-directory> [-x] [-f]
```
### Converting files from an .xspf playlist file
```sh
./app convert "/home/dhoffmann/Music/car_playlist.xspf" "/media/dhoffmann/USB_Device" -x
```
### Converting whole folder
```sh
./app convert "/home/dhoffmann/Music" "/media/dhoffmann/USB_Device"
```
### Force converting to .mp3 even if input file is one already
```sh
./app convert "/home/dhoffmann/Music" "/media/dhoffmann/USB_Device" -f
```

## Troubleshooting

### Get your USB stick recognized
- Use FAT32 filesystem
- Try different vendors and models (SanDisk Extreme works fine)
- Don't cascade to many folder levels (at most two levels)
- If none of that works, try to format your stick and give it another name
 