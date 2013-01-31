#Smush Server

## Brief

To optimize images as per the Google PageSpeed analysis.

    * https://developers.google.com/speed/docs/best-practices/payload#CompressImages

This recommends to use an image compressor such as:

    * JPG: jpegtran or jpegoptim
    * PNG: OptiPNG or PNGOUT

## Planning

After reviewing a selection of avaliable software solutions, I concluded that 
 the best solution would be to install the software on our own server (linux).

From there, I could call them from a script to process the images on the fly.

I looked to see if any existing projects were avaliable.

I found quite a few PHP wrappers, that would call the Yahoo Smushit API, but I
 felt it would be much slower and less reliable than using local software.

Upon closer inspection I discovered that the tools used by Yahoo Smush.it tool
 are the same ones recommended by Google PageSpeed.

    * http://developer.yahoo.com/yslow/smushit/faq.html

    WHAT TOOLS DOES SMUSH.IT USE TO SMUSH IMAGES?

    We have found many good tools for reducing image size. Often times these
     tools are specific to particular image formats and work much better in
     certain circumstances than others. To "smush" really means to try many
     different image reduction algorithms and figure out which one gives the
     best result.
    These are the algorithms currently in use:
    ImageMagick: to identify the image type and to convert GIF files to PNG
     files.
    pngcrush: to strip unneeded chunks from PNGs. We are also experimenting
     with other PNG reduction tools such as pngout, optipng, pngrewrite.
     Hopefully these tools will provide improved optimization of PNG files.
    jpegtran: to strip all metadata from JPEGs (currently disabled) and try
     progressive JPEGs.
    gifsicle: to optimize GIF animations by stripping repeating pixels in
     different frames.
    More information about the smushing process is available at the Optimize
     Images section of Best Practices for High Performance Web pages.
    If there is an image reduction tool you think we should add to Smush.it,
     please post your suggestion to the Yahoo! Exceptional Performance group.

What I wanted was my own version of this.

## Implementation

I discovered this smush server by colorhook here:

    * https://github.com/colorhook/smush-server/

It's very similar to the Yahoo Smush.it tool and does the same things, so it
 was a good starting point.

I forked the project and started cleaning up and redesigning it to work in
 exactly the same way as the original Smush.it API so it would work with
 existing wrappers.

## Prerequisite

###Make directories

Upload - for incoming images:
    mkdir upload
Results - for outgoing images:
    mkdir results

###Linux

Install this software:

    sudo yum install -y advancecomp gifsicle libjpeg optipng

You will also need to install jpegoptim and pngcrush from source:

#### jpegoptim
    cd /tmp
    curl -O http://www.kokkonen.net/tjko/src/jpegoptim-1.2.4.tar.gz
    tar zxf jpegoptim-*.tar.gz
    cd jpegoptim-*
    ./configure && make && make install
#### pngcrush
    cd /tmp
    curl -O http://iweb.dl.sourceforge.net/project/pmt/pngcrush/1.7.44/pngcrush-1.7.44.tar.gz
    tar zxf pngcrush-*.tar.gz
    cd pngcrush-*
    make && cp -f pngcrush /usr/local/bin

## Usage

Provided is a script called ws.php (as in "Web Service").

### Examples of usage:

Helper message
* http://www.smushit.com/ysmush.it/ws.php
Smushed response
* http://www.smushit.com/ysmush.it/ws.php?img=http://www.smushit.com/ysmush.it/css/skin/screenshot.png
Image that cannot be further smushed
* http://www.smushit.com/ysmush.it/ws.php?img=http://www.smushit.com/ysmush.it/css/skin/logo.png

All responses are in JSON format.

These are the parameters:
* img - Image you want to smush (required)
* id - Helps you identify the response if you send requests in a batch (optional)
* ~task - Allows you to group together a bunch of images if you want to get the zip file after that. Make that as unique as possible. (optional)~

### Example scenario

~Uploading two images with the same task identifier:

* http://www.smushit.com/ysmush.it/ws.php?task=mytask&img=http://example.org/image1.png
* http://www.smushit.com/ysmush.it/ws.php?task=mytask&img=http://example.org/image2.png

To get the zipped result go to:

* http://www.smushit.com/ysmush.it/zip.php?task=mytask~

#EOF