# MacUpdate    
MacUpdate is a command line tool written to ease the management of software updates on macOS, and is primarily intended for people administering multiple macOS systems through Jamf, Puppet, or other scripting.    
    
Updates that require either a restart or shutdown can be easily segregated, most commands can provide JSON output for easy use in scripts, and additional conditions are used or reported to help determine if updates will or can be installed.    
    
This tool does not install, cache, or check for updates on its own; it uses the built-in `softwareupdate` command.    

## Compatibility    
 This utility should be compatible with native macOS systems running High Sierra, Mojave, or Catalina. It has not been tested with Big Sur. 

This software does not have any non-native dependencies, however installation of [JQ](https://github.com/stedolan/jq) is highly recommended for parsing the JSON output in bash scripts.
    
## Installation & Basic Usage    
 It is recommended to copy the `macupdate.phar` to your utility path, and rename it, for example `/usr/local/bin/macupdate`.    
    
Usage involves first reading the rest of this README, then running one of the commands with various flags described below.  For example to run the _list_ command:    
    
 /usr/local/bin/macupdate list --recommended --restart  This will display a list of all pending updates that are recommend and require a restart.    
    
## Important Difference    
 It is important to note the one way in which MacUpdate differs from using the `softwareupdate` binary directly, which involves the usage of the `--restart` and `--shutdown` flags.    
    
Unlike `softwareupdate`, the `--restart` flag is not an indicator of whether the system should restart, but rather a way of limiting which updates are listed, downloaded, or installed:    
    
- To include updates that require a *restart*, you **must** use the `--restart` flag, which will also limit results to updates that require a restart.    
- To include updates that require a *shutdown*, you **must** use the `--shutdown` flag, which will also limit results to updates that require a shutdown.    
    
These flags can be combined if desired, in which case results will be limited to updates that have **any** of the flags used.    
    
## Command Details    

### Summary Command    
The most useful command is `summary`, which will output a count of total, recommended, restart required, and shutdown required updates, as well as the following additional information that is useful when determining if updates can and should be run.    
    
 - Console Username    
 - Is T2 Security Chip Present?    
 - Is Secure Boot Enabled?    
 - Is System on Battery Power?    
 - Is Screen Sleep Prevented?    
 - Is Encryption in Progress?    
    
|Flags  | Purpose |    
|--|--|    
| json | Output results in JSON format. |    
| no-scan | Do not scan for new updates, used cached results. |  

### Wait Command
This command is only useful when used in other scripting.  It will wait for the given number of seconds, or when conditions are met, based on the flags given at runtime.  The command can wait for the following conditions to clear:

- User Logged In
- Screen Sleep Prevented (usually indicating presentation or video)
- System is on Battery Power
- FileVault Encryption In Progress
- CPU Load is High

Only the conditions given as flags will be waited for.  Each condition is checked once per second during the countdown.  Once all the conditions given are cleared, or the timer has counted down, the command shows a report and exits.  An example of usage:

    macupdate wait 30 --user --power

This will wait up to 30 seconds for the system to be on AC power, and for no user to be logged in. 

If all conditions are clear, the exit code is 0.  If one or more conditions did not clear, the exit code is 1.  The report can be suppressed with the `--quiet` flag, or changed to JSON with the `--json` flag.

|Flags  | Purpose |    
|--|--|    
| json | Output results in JSON format. |    
| no-scan | Do not scan for new updates, used cached results. |    
| recommend | Only include recommended updates. |    
| restart | Only include updates requiring a restart. |    
| shutdown | Only include updates requiring a shutdown. |    
    
### Download Command    
 Using the `download` command will cache updates for later installation using the `softwareupdate` command, similar to `softwareupdate --download` This is useful if systems cannot always access the software update server.    
    
An example of where this command is useful is when users can only access the Software Update Server when connected on an internal network or connected to a VPN.  Caching the updates allows them to be downloaded while the user is connected, and wait to install them until the system isn't being actively used.    
    
|Flags  | Purpose |    
|--|--|    
| json | Output results in JSON format. |    
| no-scan | Do not scan for new updates, used cached results. |    
| recommend | Only include recommended updates. |    
| restart | Only include updates requiring a restart. |    
| shutdown | Only include updates requiring a shutdown. |    
    
### Install Command    
 Using the `install` command will install updates.  If used with without the `--restart` and `--shutdown` flags, or if no updates require either a shutdown or restart, the updates are installed individually and sequentially, making it easier to troubleshoot problems with individual updates.    
    
If the `--restart` or `--shutdown` flags are used, the OS `softwareupdate` utility is allowed to choose the order, and will restart or halt the machine after installation.    
  
Several conditions are checked to verify that it is safe to install updates:  
  
- If the system is running on battery power, updates will not install unless forced.  
- If the `--restart` or `--shutdown` flags are used and a user is logged into the GUI, updates will not install unless forced.  
- If the system is in the process of FileVault encryption, updates will not be installed even if forced  
    
|Flags  | Purpose |    
|--|--|    
| json | Output results in JSON format. |    
| no-scan | Do not scan for new updates, used cached results. |    
| force | Install even if on battery power or user is logged in. |  
| recommend | Only include recommended updates. |    
| restart | Only include updates requiring a restart. |    
| shutdown | Only include updates requiring a shutdown. |    
    
### List Command    
 Using the `list` command will list any pending updates, along with the size, and whether the update requires a restart or shutdown.    
    
|Flags  | Purpose |    
|--|--|    
| json | Output results in JSON format. |    
| no-scan | Do not scan for new updates, used cached results. |    
| quiet | Displays only the name of the updates. |    
| recommend | Only include recommended updates. |    
| restart | Only include updates requiring a restart. |    
| shutdown | Only include updates requiring a shutdown. |    
    
### Check Command    
 Using the `check` command will check if any updates exist, and is intended for use with the `--quiet` flag.  A return value of `0` is returned if updates are available, otherwise a return value of `1` is returned.    
    
This command is mostly redundant; the `summary` command is more useful, and can fulfill the same purpose.    
    
|Flags  | Purpose |    
|--|--|    
| no-scan | Do not scan for new updates, used cached results. |    
| quiet | Display no results, just a return code. |    
| recommend | Only include recommended updates. |    
| restart | Only include updates requiring a restart. |    
| shutdown | Only include updates requiring a shutdown. |