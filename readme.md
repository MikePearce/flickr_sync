## flickr_sync

I had a lot of photos to upload to flickr and the web interface has a limit on the amount you can do at one time.
I looked at a lot of solutions, none of them really met my needs, so I decided to write my own.

# What it does.
This script will run through the directory you tell it to and upload all the pictures. It will use the first level directories as collections and the second level directories as sets (or albums, the API calls them sets).

My directory needs to look like this:

```
root -
    - 2013
        - A lovely day at the beach
        - Matthews Birthday
        - Fireworks
    - 2014
        - When we went to Italy
        - The lady in the lake
        - Bandcamp
```

So, I have two sets - 2013 and 2014 - and flickr_sync will create sets (albums) for all the sub directories and then upload the photos to them. The photos will be set as private when they're uploaded.
The script will store the photos it has uploaded, as well as any sets or collections it has made to an sqlite3 db so that it doesn't upload them again the next time you upload them.

# Caveat
Unfortunately, creating and editing collections is done via undocumented API features. While the editCollections method works well for adding sets to collections, createCollection works inconsistently, so, in order for this to work, you'll need to have already created your collections (in this instance, 2013 and 2014).

# How to use it.
- Clone the repo to your local machine
- Setup your api details and other config in config.php (copy config.example.php).
- Run `%> php -f sync.php get_collections` this will access the API and store all your collections and sets in the db
- Run `%> php -f sync.php upload_photos` this will do the above, then upload all your photos.
- Wait.

If all goes well, you should have your photos uploaded, in the correct sets and in the correct collections. It will also save the original filename as a tag, should you need it later.

