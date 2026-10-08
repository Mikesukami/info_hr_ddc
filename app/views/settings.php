<?php
defined("APP_ENTRY") || exit();

/**
 * หน้าจัดการข้อมูลอ้างอิง (HTML)
 * การอ่าน/เขียนและกฎหน่วยงานอยู่ app/references.php
 * ข้อมูลจากฟอร์มส่ง POST กลับ index.php พร้อม CSRF token
 */
function settings_view(): void
{
    allowed(["admin", "superAdmin"]);
    $group = $_GET["group"] ?? "org";
    if ($group !== "activity") {
        $group = reference_group($group);
    }
    $tabs = REFERENCE_GROUPS + ["activity" => "ประเภทการบันทึก"];
    heading(
        "จัดการข้อมูลอ้างอิง",
        "เพิ่มและเปิด/ปิดรายการได้ โดยไม่ลบข้อมูลที่ใช้ในเรื่องเดิม",
    );
    ?>
    <nav class="reference-tabs" aria-label="กลุ่มข้อมูลอ้างอิง">
        <?php foreach ($tabs as $key => $label): ?>
            <a class="button <?= $group === $key ? "primary" : "" ?>"
               href="<?= h(url("settings", ["group" => $key])) ?>"
               <?= $group === $key ? 'aria-current="page"' : "" ?>><?= h($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <?php
    if ($group === "activity") {
        activity_settings_view();
        return;
    }
    $tree = $group === "org" ? organization_tree() : [];
    $paths = array_column($tree, "path", "id");
    ?>
    <section class="panel">
        <h2>เพิ่ม<?= h(REFERENCE_GROUPS[$group]) ?></h2>
        <?php if ($group === "org"): ?>
            <p class="section-copy">
                เลือก “สำนัก/กอง” เพื่อเพิ่มระดับบนสุด
                หรือเลือกหน่วยงานแม่เพื่อเพิ่มหน่วยงานย่อยได้อีก 5 ระดับ
                ฟอร์มลาจะเลือกเฉพาะสำนัก/กองเท่านั้น
            </p>
        <?php endif; ?>
        <?= form_start("reference_create") . hidden("group", $group) ?>
            <div class="reference-create-layout">
                <div class="form-grid">
                    <?= input_field("ชื่อรายการ", "label") ?>
                    <?php if ($group === "org"): ?>
                        <label>อยู่ภายใต้
                            <select name="parent_id" data-searchable>
                                <option value="">ไม่มีหน่วยงานแม่ — เพิ่มเป็นสำนัก/กอง</option>
                                <?php foreach ($tree as $org): ?>
                                    <?php if (
                                        $org["available"] &&
                                        (int) $org["org_depth"] < 5
                                    ): ?>
                                        <option value="<?= (int) $org["id"] ?>"
                                            <?= selected(old("parent_id"), $org["id"]) ?>>
                                            <?= h($org["path"]) ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>
                </div>
                <div class="form-actions">
                    <button type="submit" class="primary">เพิ่มรายการ</button>
                </div>
            </div>
        </form>
    </section>
    <?php
    // ตัวกรองเป็น parameter ของ SQL ไม่ต่อข้อความจากผู้ใช้ลง SQL โดยตรง
    $q = is_string($_GET["q"] ?? null) ? mb_substr(trim($_GET["q"]), 0, 200) : "";
    $state = in_array($_GET["state"] ?? "", ["0", "1"], true) ? $_GET["state"] : "";
    $where = " WHERE group_name = ?";
    $params = [$group];
    if ($q !== "") {
        $where .= " AND label LIKE ? ESCAPE '!'";
        $params[] = "%" . str_replace(["!", "%", "_"], ["!!", "!%", "!_"], $q) . "%";
    }
    if ($state !== "") {
        $where .= " AND active = ?";
        $params[] = (int) $state;
    }
    $data = page_data("reference_values", $where, $params, "*", "id DESC");
    ?>
    <section class="panel listing-panel">
        <form method="get" class="reference-filter">
            <?= hidden("page", "settings") . hidden("group", $group) ?>
            <label>ค้นหาชื่อ<input name="q" value="<?= h(
                $q,
            ) ?>" maxlength="200" type="search"></label>
            <label>สถานะ
                <select name="state">
                    <option value="">ทุกสถานะ</option>
                    <option value="1"<?= selected($state, "1") ?>>เปิดใช้งาน</option>
                    <option value="0"<?= selected($state, "0") ?>>ปิดใช้งาน</option>
                </select>
            </label>
            <button class="primary" type="submit">ค้นหา</button>
            <a class="button" href="<?= h(
                url("settings", ["group" => $group]),
            ) ?>">ล้าง</a>
        </form>
        <div class="server-table-scroll" tabindex="0" role="region" aria-label="ข้อมูลอ้างอิง เลื่อนซ้ายขวาได้">
            <table class="server-table">
                <thead><tr><th>ชื่อรายการ</th><?php if (
                    $group === "org"
                ): ?><th>ลำดับชั้น / เส้นทาง</th><?php endif; ?><th>สถานะ</th><th>จัดการ</th></tr></thead>
                <tbody>
                    <?php foreach ($data["items"] as $item): ?>
                        <tr>
                            <td><?= h($item["label"]) ?></td>
                            <?php if ($group === "org"): ?>
                                <td><?= (int) $item["org_depth"] === 0
                                    ? "สำนัก/กอง"
                                    : "ต่ำกว่าสำนัก/กอง " .
                                        (int) $item["org_depth"] .
                                        " ระดับ" ?>
                                    <small class="subline"><?= h(
                                        $paths[$item["id"]] ?? $item["label"],
                                    ) ?></small>
                                </td>
                            <?php endif; ?>
                            <td><?= active_badge((bool) $item["active"]) ?></td>
                            <td>
                                <div class="reference-row-actions">
                                <?= form_start("reference_active") .
                                    hidden("id", $item["id"]) .
                                    hidden("group", $group) .
                                    hidden("active", $item["active"] ? "0" : "1") ?>
                                    <button type="submit"><?= $item["active"]
                                        ? "ปิดใช้งาน"
                                        : "เปิดใช้งาน" ?></button>
                                </form>
                                <?= reference_delete_form((int) $item["id"], $group, $item["label"]) ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$data["items"]): ?><tr><td colspan="<?= $group === "org"
    ? 4
    : 3 ?>">ไม่พบรายการ</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php paginate($data); ?>
    </section>
    <?php
}

function activity_settings_view(): void
{
    ?>
    <section class="panel">
        <h2>เพิ่มประเภทการบันทึก</h2>
        <?= form_start("type_save") ?>
            <div class="reference-create-layout">
                <div class="form-grid">
                    <?= input_field("ชื่อประเภทใหม่", "name", "", "text", true, 120) ?>
                    <?= select_field("แบบฟอร์ม", "form_kind", [
                        "study" => "การศึกษา",
                        "course" => "หลักสูตร / สถานที่",
                    ]) ?>
                </div>
                <div class="form-actions">
                    <button type="submit" class="primary">เพิ่มประเภท</button>
                </div>
            </div>
        </form>
    </section>
    <?php $data = page_data("activity_types", "", [], "*", "id"); ?>
    <section class="panel listing-panel">
        <div class="server-table-scroll" tabindex="0" role="region" aria-label="ประเภทการบันทึก เลื่อนซ้ายขวาได้">
            <table class="server-table">
                <thead><tr><th>ประเภท</th><th>แบบฟอร์ม</th><th>สถานะ</th><th>จัดการ</th></tr></thead>
                <tbody>
                    <?php foreach ($data["items"] as $type): ?>
                        <tr>
                            <td><?= h($type["name"]) ?></td>
                            <td><?= $type["form_kind"] === "study"
                                ? "การศึกษา"
                                : "หลักสูตร / สถานที่" ?></td>
                            <td><?= active_badge((bool) $type["active"]) ?></td>
                            <td>
                                <div class="reference-row-actions">
                                <?= form_start("type_active") .
                                    hidden("id", $type["id"]) .
                                    hidden("active", $type["active"] ? "0" : "1") ?>
                                <button><?= $type["active"]
                                    ? "ปิดใช้งาน"
                                    : "เปิดใช้งาน" ?></button>
                                </form>
                                <?= reference_delete_form((int) $type["id"], "activity", $type["name"]) ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php paginate($data); ?>
    </section>
    <?php
}

/** ปุ่มลบแสดงเฉพาะ Super Admin; สิทธิ์จริงตรวจซ้ำใน routes และ action */
function reference_delete_form(int $id, string $group, string $label): string
{
    if (($GLOBALS["user"]["role"] ?? "") !== "superAdmin") {
        return "";
    }
    return form_start($group === "activity" ? "type_delete" : "reference_delete") .
        hidden("id", $id) . hidden("group", $group) . hidden("delete_confirmed", "0") .
        '<button type="submit" class="reference-delete" aria-label="' . h("ลบ " . $label) .
        '">ลบ</button></form>';
}
