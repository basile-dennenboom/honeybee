# Cloud disks

In v1, disks with a non-`local` driver (s3, ftp, sftp, …) are listed but marked "not measured", because `df` and `find` cannot reach them.

## Ideas

- Run a remote PHP snippet that uses the app's own `Storage::disk($name)->allFiles()` plus `size()`. This works for any Flysystem driver but makes one API call per file, which is slow and costs money on S3 for big buckets.
- S3-specific: use bucket-level metrics (CloudWatch `BucketSizeBytes`, updated daily) or S3 Storage Lens. They are cheap, but give no per-extension split.
- Cache cloud results and refresh them much less often than local disks (e.g. hourly).
- Cloud disks have no fixed "capacity". Show used size only, without a partition bar.
