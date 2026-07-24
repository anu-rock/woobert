#!/usr/bin/env node
/**
 * Bump the Hoobert release version across every file that carries it.
 *
 * Usage (run from plugin/hoobert):
 *   npm run bump -- <version>     e.g. npm run bump -- 0.2.0
 *   npm run bump -- patch|minor|major
 *
 * Touches: hoobert.php (header + HOOBERT_VERSION), package.json, readme.txt
 * (Stable tag), and ../../version.txt. Each edit is a targeted regex so
 * surrounding formatting is preserved; the run aborts if any expected version
 * marker is missing, so partial bumps can't ship.
 *
 * The release workflow calls this with an explicit version from release-please;
 * running it by hand remains a supported escape hatch.
 */

'use strict';

const fs = require('fs');
const path = require('path');

const pluginDir = path.resolve(__dirname, '..');
const repoRoot = path.resolve(pluginDir, '..', '..');

const SEMVER = /^\d+\.\d+\.\d+$/;

/** Read the current version from package.json (the source of truth). */
function currentVersion() {
	const pkg = JSON.parse(fs.readFileSync(path.join(pluginDir, 'package.json'), 'utf8'));
	if (!pkg.version) {
		throw new Error('No "version" field in package.json');
	}
	return pkg.version;
}

/** Resolve a CLI arg (explicit version or patch/minor/major) to a version string. */
function resolveVersion(arg, from) {
	if (!arg) {
		throw new Error('Provide a version or one of: patch, minor, major');
	}
	if (['patch', 'minor', 'major'].includes(arg)) {
		if (!SEMVER.test(from)) {
			throw new Error(`Cannot ${arg}-bump non-semver current version "${from}"`);
		}
		let [major, minor, patch] = from.split('.').map(Number);
		if (arg === 'major') { major += 1; minor = 0; patch = 0; }
		else if (arg === 'minor') { minor += 1; patch = 0; }
		else { patch += 1; }
		return `${major}.${minor}.${patch}`;
	}
	if (!SEMVER.test(arg)) {
		throw new Error(`"${arg}" is not a valid MAJOR.MINOR.PATCH version`);
	}
	return arg;
}

/**
 * Each edit names a file, a regex with the version in capture group 2 (framed by
 * groups 1 and 3), and a label. The regex must match exactly once.
 */
function edits(version) {
	return [
		{
			label: 'hoobert.php (plugin header)',
			file: path.join(pluginDir, 'hoobert.php'),
			re: /^(\s*\*\s*Version:\s*)(\d+\.\d+\.\d+)(\s*)$/m,
			next: version,
		},
		{
			label: 'hoobert.php (HOOBERT_VERSION)',
			file: path.join(pluginDir, 'hoobert.php'),
			re: /(define\(\s*'HOOBERT_VERSION',\s*')(\d+\.\d+\.\d+)(' \);)/,
			next: version,
		},
		{
			label: 'package.json',
			file: path.join(pluginDir, 'package.json'),
			re: /("version":\s*")(\d+\.\d+\.\d+)(")/,
			next: version,
		},
		{
			label: 'readme.txt (Stable tag)',
			file: path.join(pluginDir, 'readme.txt'),
			re: /^(Stable tag:\s*)(\d+\.\d+\.\d+)(\s*)$/m,
			next: version,
		},
		{
			label: 'version.txt',
			file: path.join(repoRoot, 'version.txt'),
			re: /^()(\d+\.\d+\.\d+)(\s*)$/m,
			next: version,
		},
	];
}

function main() {
	const from = currentVersion();
	const to = resolveVersion(process.argv[2], from);

	const planned = edits(to);

	// package.json alone matching `to` isn't enough: a half-applied bump (or a
	// re-run against a partly-synced release branch) must still be completed.
	const settled = planned.every((edit) => {
		const match = fs.readFileSync(edit.file, 'utf8').match(edit.re);
		return match && match[2] === to;
	});
	if (settled) {
		console.log(`Already at ${to} everywhere; nothing to do.`);
		return;
	}

	// Validate every marker first so a bad match never leaves files half-bumped.
	for (const edit of planned) {
		const text = fs.readFileSync(edit.file, 'utf8');
		if (!edit.re.test(text)) {
			throw new Error(`Could not find version marker in ${edit.label} (${edit.file})`);
		}
	}

	for (const edit of planned) {
		const text = fs.readFileSync(edit.file, 'utf8');
		const updated = text.replace(edit.re, `$1${edit.next}$3`);
		fs.writeFileSync(edit.file, updated);
		console.log(`  ${edit.label}`);
	}

	console.log(`\nBumped ${from} -> ${to} across ${planned.length} locations.`);
	console.log('Review with `git diff`, then commit.');
}

try {
	main();
} catch (err) {
	console.error(`bump-version: ${err.message}`);
	process.exit(1);
}
