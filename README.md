# Fast atomic FTP deploys

Modern PHP applications consist of thousands of files. To install such application to a shared web host without SSH access, one would typically extract an archive with the application locally and then upload the files to the server using FTP. But this is extremely slow and, if we are updating an already installed app, it might get inconsistent.

Instead, `fafd` will upload the archive unextracted alongside with a PHP script, and then run the script using HTTP request. The script will take care of unpacking the archive on the server and swapping the original directory with the new one.

It is still not completely atomic – that would require using symlinks – but if you need this program, you probably can handle the fraction-of-a-second downtime.

## Prerequisites

- You have FTP write access to the parent directory of the target location.
- It is possible to run PHP script uploaded to the target location using HTTP URI.
- The web server/CGI/… timeout is long enough for the archive to be extracted.
- You need `gio` program and `gvfs` installed to be able to upload the files.
- Only ZIP archives are currently supported.

## Usage

Create a [TOML](https://toml.io/en/) file called `fafd.toml`, containing something like the following:

```toml
[deployments.default]
upload_uri = "ftps://w12345@12345.w42.mywebhost.net/www/domains/entries.mywebsite.org/"
web_uri = "https://entries.mywebsite.org/"
# Path under `upload_uri`, that is accessible through HTTP, to upload the script to.
# (optional, defaults to the same directory as `upload_uri`)
www_root = "www"
# Files to copy over from the old directory (optional)
transfer_files = [
    "app/config/private.neon",
]
# Extra arguments passed to requests when accessing the activate.php script (optional)
extra_post_args = { cookies.knock = "knock" }
```

Then, run `fafd --file=build.zip` command in the directory.

## Installation

You can get it easily using [Nix](https://nixos.org/) package manager:

- With legacy Nix, you can install the package to your user profile using: `nix-env -f https://github.com/jtojnar/fafd/archive/main.tar.gz -iA fafd`
- With experimental Flakes-enabled Nix, you can just execute `nix run github:jtojnar/fafd` to run without installing anything.
