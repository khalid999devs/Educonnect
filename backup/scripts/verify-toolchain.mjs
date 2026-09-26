import { execFileSync } from "node:child_process";
import { readFile } from "node:fs/promises";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const repositoryRoot = join(dirname(fileURLToPath(import.meta.url)), "..");

async function readText(path) {
  return (await readFile(join(repositoryRoot, path), "utf8")).trim();
}

function fail(message) {
  process.stderr.write(`Toolchain verification failed: ${message}\n`);
  process.exitCode = 1;
}

function readCommandVersion(command, args, pattern) {
  try {
    const output = execFileSync(command, args, {
      encoding: "utf8",
      stdio: ["ignore", "pipe", "pipe"],
    }).trim();

    return pattern.exec(output)?.[1];
  } catch {
    return undefined;
  }
}

const packageJson = JSON.parse(await readText("package.json"));
const nodeVersion = await readText(".node-version");
const nvmVersion = await readText(".nvmrc");
const ciWorkflow = await readText(".github/workflows/api-ci.yml");
const toolVersions = Object.fromEntries(
  (await readText(".tool-versions"))
    .split("\n")
    .map((line) => line.trim().split(/\s+/, 2)),
);

const packageManagerMatch = /^pnpm@(\d+\.\d+\.\d+)$/.exec(
  packageJson.packageManager ?? "",
);

if (nodeVersion !== nvmVersion) {
  fail(`.node-version (${nodeVersion}) and .nvmrc (${nvmVersion}) differ`);
}

if (nodeVersion !== toolVersions.nodejs) {
  fail(
    `.node-version (${nodeVersion}) and .tool-versions nodejs (${toolVersions.nodejs}) differ`,
  );
}

if (packageJson.engines?.node !== nodeVersion) {
  fail(
    `package.json engines.node (${packageJson.engines?.node}) must equal ${nodeVersion}`,
  );
}

if (!packageManagerMatch) {
  fail("packageManager must contain an exact pnpm semantic version");
}

const pnpmVersion = packageManagerMatch?.[1];

if (packageJson.engines?.pnpm !== pnpmVersion) {
  fail(
    `package.json engines.pnpm (${packageJson.engines?.pnpm}) must equal ${pnpmVersion}`,
  );
}

if (process.versions.node !== nodeVersion) {
  fail(`active Node ${process.versions.node} does not match ${nodeVersion}`);
}

const userAgent = process.env.npm_config_user_agent ?? "";
const activePnpmVersion = /(?:^|\s)pnpm\/(\d+\.\d+\.\d+)/.exec(userAgent)?.[1];

if (activePnpmVersion !== pnpmVersion) {
  fail(`active pnpm ${activePnpmVersion ?? "unknown"} does not match ${pnpmVersion}`);
}

if (!/^\d+\.\d+\.\d+$/.test(toolVersions.php ?? "")) {
  fail(".tool-versions php must contain an exact patch version");
}

if (!/^\d+\.\d+\.\d+$/.test(toolVersions.composer ?? "")) {
  fail(".tool-versions composer must contain an exact patch version");
}

if (!/^\d+\.\d+$/.test(toolVersions.postgres ?? "")) {
  fail(".tool-versions postgres must contain an exact major.minor release");
}

const activePhpVersion = readCommandVersion(
  "php",
  ["-r", "echo PHP_VERSION;"],
  /^(\d+\.\d+\.\d+)$/,
);

if (activePhpVersion !== toolVersions.php) {
  fail(
    `active PHP ${activePhpVersion ?? "unknown"} does not match ${toolVersions.php}`,
  );
}

const activeComposerVersion = readCommandVersion(
  "composer",
  ["--version", "--no-ansi"],
  /Composer version (\d+\.\d+\.\d+)/,
);

if (activeComposerVersion !== toolVersions.composer) {
  fail(
    `active Composer ${activeComposerVersion ?? "unknown"} does not match ${toolVersions.composer}`,
  );
}

for (const expectedCiPin of [
  `php-version: '${toolVersions.php}'`,
  `tools: composer:${toolVersions.composer}`,
  `image: postgres:${toolVersions.postgres}`,
]) {
  if (!ciWorkflow.includes(expectedCiPin)) {
    fail(`CI workflow must contain exact pin: ${expectedCiPin}`);
  }
}

if (process.exitCode === undefined) {
  process.stdout.write(
    `Active toolchain verified: Node ${nodeVersion}, pnpm ${pnpmVersion}, PHP ${toolVersions.php}, Composer ${toolVersions.composer}; CI PostgreSQL pin ${toolVersions.postgres}\n`,
  );
}
