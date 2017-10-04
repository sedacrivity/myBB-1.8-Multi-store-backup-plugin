# myBB-1.8-Multi-store-backup-plugin
A small database backup plugin which provides a task to 
- backup the database content in the default storage location on the file system (same functionality as the default task)
- optional storage on an FTP location

The FTP settings can be configured and include:
- FTP remote server address
- FTP username & password
- Remote folder
- Optional filename prefix 
- Optional dynamic generation of year-month subfolders

English is currently the only available supported language.

Please note that backups of a large database might take a while to perform and might thus impact your users browsing the forum ( the first user that uses the forum takes 'a hit' while the tasks are being executed).  
If you have a very large database then I would not recommend using the default myBB task system for taking backups but use a real background job on your host system.




