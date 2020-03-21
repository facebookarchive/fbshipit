# Using This Demo

This folder contains the files necessary to export just the `fb-examples` directory
(and the LICENSE) of the `fbshipit` repository to another repo. It renames
`fb-examples` to `examples` and filters out all other files.

This is a simple demo that demonstrates a common use case for ShipIt. ShipIt is much
more powerful than just the functionality shown here, please see the files in
[fb-examples/](fb-examples) for some more advanced use cases.

For this demo, we use git and GitHub repositories as both the source and destination,
but this is just one use case for ShipIt. The source and destination could easily be git
repos hosted outside of GitHub or even Mercurial repositories. GitHub is used here for
convenience and familiarity.

Before starting, clone the `fbshipit` repository and navigate to the `demo` folder.

## 1. GitHub Credentials

FBShipIt needs to know how to know how to authenticate with GitHub to push commits.
Since the source repo (`fbshipit`) is public no authentication is required. But to
push to the destination repo we'll need to authenticate.

Open [DemoGitHubUtils.hack](DemoGitHubUtils.hack) in your editor and change the
`$committer_user` to your GitHub username. The `$committer_email` and `$commmitter_name`
may also be changed to suit preference, but this is not necessary.

Generate a GitHub access token with the "repo" scope in the
[GitHub Developer Settings](https://github.com/settings/tokens) and copy the token into
the `access_token` field in the `getCredentialsForProject`.

Do NOT check this token into your version control system. For production use this value
should be queried from some secure storage system. *Only for this demo is a string
constant a valid configuration. Any other use is a security risk.*

## 2. Source Repository

Open the [DemoSourceRepoInitPhase.hack](DemoSourceRepoInitPhase.hack) file and examine
the URL used for cloning: `"https://github.com/facebook/fbshipit.git"`. Note that this
can be replaced with any URL that resolves to a git repo. In the case of an `hg` source
repoistory the `$command` variable can be changed to the equivilent `hg clone` URL and
FBShipIt will automatically handle that repository type.

## 3. Filters

Open the [run\_shipit.hack](run_shipit.hack) file and look at the `getPathMappings`
function. This function returns a `dict` that will be used to move folders around.
The left side is source, and the right is destination. Paths are evaulated from
top to bottom which allows complex arrangements where inner folders are moved out
from under outer folders while the outer folder is renamed and so forth.

Some paths are also filtered out by the `stripPaths` filter. Each path in a commit
is matched against each regex in the vector, if any match that path is excluded.

In this demo, we only want the `fb-examples` folder, so all other paths are stripped.

## 4. Base Config

When the source and destination repositories are cloned, they use the paths provided
by the `ShipItBaseConfig`. For the default working dir, `/var/tmp/shipit` is a
convention while the source and destination repo names can be anything. The source
and destination names are, by convention, the names of the actual repositories.

Source roots allows the user to control where in the repository ShipIt should limit
itself to. For example, if the goal is to export only the `open_source/foo/bar` dir
then source roots could be set to that directory. This provides an automatic filter
that doesn't need to be explicitely listed out like `stripPaths`.

## 5. GitHub Destination Respository

Finally, the GitHub user listed in `DemoGitHubUtils` needs to create a new empty
repository with the name `fbshipit-demo`. Another name can be used, just be sure to
update the string constant in the constructor for `ShipItGitHubInitPhase` in
`run_shipit.hack`.

## 6. Creating the Destination Repo

For demonstration purposes, we are going to create the new repository at a point
in the past to demonstrate syncing commits in the next step:

```bash
hhvm run_shipit.hack --create-new-repo-from-commit=ba89254500b2ad692991fc7791737b7c3562fa1b
```

For real life usages you might prefer to just use the current HEAD:

```bash
hhvm run_shipit.hack --create-new-repo
```

Either way, if successful this command will create a new destination repository
and tell you where it is. `cd` to that directory and add the GitHub repo created
above as the `origin` and push:

```bash
git remote add origin git@github.com:CHANGEME/fbshipit-demo.git
git push origin master
```

Congradulations! ShipIt has now been configured and the destination respository has
been initialized.

## 7. Future Runs

When new commits are added all that needs to be done is run shipit with no arguments.
ShipIt adds tracking information to each commit automatically:

```bash
hhvm run_shipit.hack
```

If you took the first option in step 6, you can do this now and shipit will sync all
commits since `ba8925`. In the output some commits may be marked as "SKIPPED", this
means that after applying `stripPaths` that commit was empty so it was not synced
to the destination repo
