#!/bin/bash
shopt -s nullglob
for image_file in [0-9][0-9].jpg [0-9][0-9].JPG [0-9][0-9].jpeg [0-9][0-9].png [0-9][0-9][0-9].jpg [0-9][0-9][0-9].JPG [0-9][0-9][0-9].jpeg [0-9][0-9][0-9].png [0-9][0-9][0-9][0-9].jpg [0-9][0-9][0-9][0-9].JPG [0-9][0-9][0-9][0-9].jpeg [0-9][0-9][0-9][0-9].png 
do
filename="${image_file%.*}"
file80="$filename-80px.jpg"
file180="$filename-180px.jpg"
file200="$filename-200px.jpg"
file400="$filename-400px.jpg"
file2000="$filename-2000px.jpg"
if [ ! -f $file80 ]; then
    echo "$image_file => $file80"
    #gm convert $image_file -resize x80 $file80
fi
if [ ! -f $file200 ]; then
    echo "$image_file => $file200"
    #gm convert $image_file -resize x200 $file200
fi
if [ ! -f $file400 ]; then
    echo "$image_file => $file400"
    #gm convert $image_file -resize x400 $file400
fi
if [ ! -f $file2000 ]; then
    echo "$image_file => $file2000"
    #gm convert $image_file -resize x2000 $file2000
fi
extension="${image_file##*.}"
if [ "$extension" == "JPG" ] || [ "$extension" = "jpeg" ]; then
  echo "extension is '$extension', should be jpg"
  # TODO: change extension, but careful that old versions may remain on FTP server
fi
done
