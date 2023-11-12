<?php

namespace Univer;

#
# Algo
#
# Detection last release version of given repo.
# Befor "git commit" user must save commit hash with command
# "git rev-parse HEAD >last_commit.sha" to file in the project`s root directory (where LICENSE is located).
# After pushing it goes to repo and can be read by this class. It became previous (!) SHA hash (before commit).
#
# Finding out child of that hash in commits, comparing child`s SHA and hash of release
# gives us the release version of your product.
#

class releaseVer
{
    private $github_url = "https://api.github.com/repos/";	// https://api.github.com/repos/OWNER/REPO/releases
	public $error, $last_commit_hash_path;

	function __construct($last_commit_hash_path = null) {
		$this->last_commit_hash_path = $last_commit_hash_path ?? "..".DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."last_commit.sha";
	}

    /**
	* @param  string $repo_name, i.e. "mr-older/univer"
	*/
	public function getVersion($repo_name) {
		$this->error = "";

		if(!file_exists($this->last_commit_hash_path)) {
			$this->error = "Last commit hash not found @ {$this->last_commit_hash_path}";
			return false;
		}

		if(empty(($last_commit_hash = file_get_contents($this->last_commit_hash_path)))) {
			$this->error = "Couldn`t get hash from last commit sha file @ {$this->last_commit_hash_path}";
			return false;
		}

		if(!($ch = curl_init())) {
			error('Failed to create curl object, may be CURL is not enabled');
			return false;
		}

		curl_setopt($ch, CURLOPT_URL, ($url = $this->github_url.$repo_name."/commits"));
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_USERAGENT, "mr-older/univer");	// github req

		if(empty($commits = curl_exec($ch))) {
			$this->error = "Couldn`t get commits @ $url";
			return false;
		}

		$commits = json_decode($commits, true);
		$last_commit_hash = trim($last_commit_hash);

		foreach((array) $commits as $commit) {
			if($commit['parents']['sha'] != $last_commit_hash) {
				continue;
			}
			$release_commit_hash = $commit['sha'];
			break;
		}

		if(empty($release_commit_hash)) {
			$this->error = "Couldn`t find commit for $last_commit_hash";
			return false;
		}

		curl_setopt($ch, CURLOPT_URL, ($url = $this->github_url.$repo_name."/releases"));

		if(empty($releases = curl_exec($ch))) {
			$this->error = "Couldn`t get releases @ $url";
			return false;
		}

		$releases = json_decode($releases, true);

		foreach((array) $releases as $release) {
			if($release['commit']['sha'] != $last_commit_hash) continue;
			return $release['name'];
		}

		$this->error = "No release for commit $release_commit_hash";
		return false;
	}
}
