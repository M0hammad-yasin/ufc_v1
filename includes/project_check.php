<?php
/**
 * includes/project_check.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Reusable hook: checks whether a project_name already exists in the database.
 * Import this file anywhere you need the check — it has no side-effects on its
 * own; just provides two functions.
 */

/**
 * Checks if a project_name is already taken in the assessments table.
 *
 * @param PDO      $pdo        Active database connection.
 * @param string   $name       The project name to check.
 * @param int|null $excludeId  Optional assessment ID to exclude (for edit flows).
 * @return bool  TRUE if the name is already in use.
 */
function checkProjectExists(PDO $pdo, string $name, ?int $excludeId = null): bool
{
    $name = trim($name);
    if ($name === '') {
        return false;
    }

    if ($excludeId !== null) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM assessments WHERE project_name = ? AND id != ?"
        );
        $stmt->execute([$name, $excludeId]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM assessments WHERE project_name = ?"
        );
        $stmt->execute([$name]);
    }

    return (int)$stmt->fetchColumn() > 0;
}

/**
 * Renders the "Project Already Exists" warning modal HTML string.
 * Call echo renderProjectExistsModal() wherever you need it.
 */
function renderProjectExistsModal(): string
{
    return '
    <div id="projectExistsModal"
         class="fixed inset-0 z-[200] bg-black/60 backdrop-blur-sm flex items-center justify-center transition-opacity duration-300">
        <div class="bg-[#0d1f3c] border border-[#1a3a5c] rounded-xl p-6 shadow-2xl max-w-sm w-full mx-4 text-center">
            <div class="text-4xl mb-4">⚠️</div>
            <h3 class="text-xl font-bold text-white mb-2">Project Already Exists</h3>
            <p class="text-slate-400 mb-6">A project with this name already exists in the system. Please choose a different project name.</p>
            <button type="button"
                    onclick="document.getElementById(\'projectExistsModal\').remove()"
                    class="w-full px-4 py-2.5 rounded-lg font-semibold text-white bg-[#1a3a5c] hover:bg-[#234d7a] transition-colors">
                Close
            </button>
        </div>
    </div>
    <script>
        document.addEventListener("keydown", function handler(e) {
            if (e.key === "Escape") {
                var m = document.getElementById("projectExistsModal");
                if (m) { m.remove(); document.removeEventListener("keydown", handler); }
            }
        });
    </script>
    ';
}
