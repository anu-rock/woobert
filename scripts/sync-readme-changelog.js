#!/usr/bin/env node
/**
 * Mirror the newest CHANGELOG.md section into readme.txt's `== Changelog ==`.
 *
 * Usage (run from the repo root):
 *   node scripts/sync-readme-changelog.js <version>
 *
 * release-please owns CHANGELOG.md but knows nothing about the WordPress.org
 * readme format, so we translate: markdown `### Features` becomes `**Features**`
 * and commit links are stripped, since wp.org renders the changelog as plain
 * text with light markup. Re-running for a version already present rewrites
 * that entry in place, so the release PR can be regenerated safely.
 */

'use strict';

const fs = require('fs');
const path = require('path');

const repoRoot = path.resolve(__dirname, '..');
const changelogPath = path.join(repoRoot, 'CHANGELOG.md');
const readmePath = path.join(repoRoot, 'plugin', 'hoobert', 'readme.txt');

const SEMVER = /^\d+\.\d+\.\d+$/;
const HEADING = '== Changelog ==';

/** Pull the body of a version's section out of CHANGELOG.md. */
function extractSection(changelog, version) {
	const lines = changelog.split('\n');
	// Matches both `## [0.2.0](compare-url) (date)` and `## 0.1.0 (date)`.
	const start = lines.findIndex((line) =>
		new RegExp(`^##\\s+\\[?${version.replace(/\./g, '\\.')}\\]?[\\s(]`).test(line)
	);
	if (start === -1) {
		throw new Error(`No section for ${version} in CHANGELOG.md`);
	}
	let end = lines.findIndex((line, i) => i > start && /^##\s/.test(line));
	if (end === -1) {
		end = lines.length;
	}
	return lines.slice(start + 1, end);
}

/** Convert markdown changelog lines to the readme.txt dialect. */
function toReadmeEntry(version, sectionLines) {
	const body = [];
	for (const line of sectionLines) {
		const group = line.match(/^###\s+(.*)$/);
		if (group) {
			body.push('', `**${group[1].trim()}**`, '');
			continue;
		}
		if (/^\s*\*\s/.test(line)) {
			// Drop trailing commit/PR links: `* thing ([abc1234](https://...))`
			const text = line
				.replace(/^\s*\*\s+/, '')
				.replace(/\s*\(\[[^\]]+\]\([^)]+\)\)\s*$/, '')
				.trim();
			if (text) {
				body.push(`* ${text}`);
			}
		}
	}

	// Collapse the blank lines the group headings introduce.
	const cleaned = [];
	for (const line of body) {
		if (line === '' && (cleaned.length === 0 || cleaned[cleaned.length - 1] === '')) {
			continue;
		}
		cleaned.push(line);
	}
	while (cleaned[cleaned.length - 1] === '') {
		cleaned.pop();
	}

	return [`= ${version} =`, '', ...cleaned].join('\n');
}

/** Insert (or replace) the entry directly under the `== Changelog ==` heading. */
function spliceIntoReadme(readme, version, entry) {
	const headingAt = readme.indexOf(HEADING);
	if (headingAt === -1) {
		throw new Error(`readme.txt has no "${HEADING}" heading`);
	}

	const head = readme.slice(0, headingAt + HEADING.length);
	let tail = readme.slice(headingAt + HEADING.length);

	// Idempotency: if this version is already listed, cut the old entry out.
	const existing = new RegExp(`\\n= ${version.replace(/\./g, '\\.')} =\\n[\\s\\S]*?(?=\\n= \\d+\\.\\d+\\.\\d+ =\\n|$)`);
	tail = tail.replace(existing, '');

	return `${head}\n\n${entry}\n${tail.replace(/^\n+/, '\n')}`;
}

function main() {
	const version = process.argv[2];
	if (!version || !SEMVER.test(version)) {
		throw new Error('Provide the release version, e.g. 0.2.0');
	}

	const changelog = fs.readFileSync(changelogPath, 'utf8');
	const readme = fs.readFileSync(readmePath, 'utf8');

	const entry = toReadmeEntry(version, extractSection(changelog, version));
	fs.writeFileSync(readmePath, spliceIntoReadme(readme, version, entry));

	console.log(`readme.txt: added changelog entry for ${version}`);
}

try {
	main();
} catch (err) {
	console.error(`sync-readme-changelog: ${err.message}`);
	process.exit(1);
}
