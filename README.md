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
```sh
./app convert -s <source> -t <target> [-x]
```
### Converting files from an .xspf playlist file
```sh
./app convert -s "/home/dhoffmann/Music/car_playlist.xspf" -t "/media/dhoffmann/USB_Device" -x
```
### Converting whole folder
```sh
./app convert -s "/home/dhoffmann/Music" -t "/media/dhoffmann/USB_Device"
```