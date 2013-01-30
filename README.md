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

I found quite a few PHP wrappers, that would call the Yahoo Smush.it API, but I
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

## Conclusion

I soon realised that this was futile due to the following reasons:

    1. Google does not practice what it preaches.
        Analyze: https://developers.google.com/speed/pagespeed/

        Optimizing the following images could reduce their size by 16.5KiB (38% reduction).
        Losslessly compressing https://developers.google.com/.../banner-carusel-pagespeed.png could save 10.1KiB (44% reduction). See optimized content
        Losslessly compressing https://developers.google.com/.../developers-logo.png could save 3.4KiB (50% reduction). See optimized content
        Losslessly compressing https://developers.google.com/.../google-logo.png could save 1.8KiB (17% reduction). See optimized content
        Losslessly compressing https://developers.google.com/.../developers-logo-footer.png could save 1.2KiB (60% reduction). See optimized content

    2. The actual smush.it was unable to optimise these images as suggested:

        https://developers.google.com/speed/images/banner-carusel-pagespeed.png
        https://developers.google.com/_static/images/developers-logo.png
        https://developers.google.com/_static/images/google-logo.png
        https://developers.google.com/_static/images/developers-logo-footer.png

        Smush.it did not find any saving of your image(s).

Until these issues are addressed, I see no point in continuing this project.

## Summary

Although I didn't complete what I set out to do, I felt it was a worthwhile
 quest. Perhaps I'll revisit this in the future to finish it.

Either that, or Yahoo should just open source the project.