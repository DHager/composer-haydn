# composer-haydn

A development-mode tool for selectively overriding Composer settings. In
particular, it is useful when you are developing multiple Composer projects
that interrelate and are on git repositories you have cloned to your local
machine.

The `haydn.json` file allows you to temporarily override, add, or remove
package-versions repositories which are defined in `composer.json`. This
allows you to point to local repositories and dev-branches on those
repositories.

# Example haydn.json

This example adds/overrides the `mycorp/foo` and `mycorp/baz` dependencies,
removes the `mycorp/bar` dependency, and overrides any custom repositories
with an url `git@repo.corp.net:shared/foo/.git` to point to a local git
folder. (If none match, it will just add a new repository.)

    {
        "override-require": {
            "mycorp/foo" : "dev-featurebranch",
            "mycorp/bar" : ""
        },
        "override-require-dev": {
            "mycorp/baz" : "dev-featurebranch",
        },
        "override-repositories": {
            "git@repo.corp.net:shared/foo/.git" : {
                "type" : "vcs",
                "url" : "/home/jdoe/code/foo/"
            }
        }
    }

# Setup

* Add `haydn.php` and make it executable with `chmod +x`
* Create a haydn.json file
* Either `composer.phar` or `composer` must be in your path and executable.

# Usage

This command will create a modified temporary copy of `composer.json` and
trigger Composer to act upon the temporary copy.

    haydn.php update

All command-line arguments to haydn are passed on to composer. As far as
Composer is concerned, the only difference is that it's been told to operate
just this once on a different `composer.json` file than normal.



