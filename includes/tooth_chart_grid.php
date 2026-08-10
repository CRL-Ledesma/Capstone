<?php
/**
 * tooth_chart_grid.php  — Flat-grid odontogram matching paper chart
 *
 * Paper chart layout (Amparo Dental Clinic):
 *   Row 1 (primary upper):  E D C B A | A B C D E
 *   Row 2 (permanent upper): 8 7 6 5 4 3 2 1 | 1 2 3 4 5 6 7 8
 *                             ← patient right  |  patient left →
 *   GUM LINE
 *   Row 3 (permanent lower): 8 7 6 5 4 3 2 1 | 1 2 3 4 5 6 7 8
 *   Row 4 (primary lower):  E D C B A | A B C D E
 *
 * Internally we still store FDI codes (18,17..11,21..28 etc.) in the DB,
 * but we DISPLAY them as quadrant numbers 1-8.
 *
 * MODES
 *   mode = 'input'   (default)  → interactive, syncs to hidden inputs
 *   mode = 'display'            → read-only, colors from $chart_teeth array
 *
 * INPUT MODE — variables the parent must define BEFORE including:
 *   $tc_input_name   = 'tooth_number'
 *   $ts_select_id    = 'tooth_status_select'
 *   $tc_initial      = ''   (comma list of pre-selected FDI teeth)
 *
 * DISPLAY MODE — variable the parent must define BEFORE including:
 *   $chart_teeth     = []   assoc: FDI code => status string
 *   $chart_uid       = 'chart_123'
 */

$tc_mode       = $tc_mode       ?? 'input';
$tc_input_name = $tc_input_name ?? 'tooth_number';
$ts_select_id  = $ts_select_id  ?? 'tooth_status_select';
$tc_initial    = $tc_initial    ?? '';
$chart_teeth   = $chart_teeth   ?? [];
$chart_uid     = $chart_uid     ?? 'chart_' . uniqid();

/*
 * FDI codes in display order (right to left within each half)
 *   Upper right quadrant 1: 18 17 16 15 14 13 12 11  (displayed as 8 7 6 5 4 3 2 1)
 *   Upper left  quadrant 2: 21 22 23 24 25 26 27 28  (displayed as 1 2 3 4 5 6 7 8)
 *   Lower right quadrant 4: 48 47 46 45 44 43 42 41  (displayed as 8 7 6 5 4 3 2 1)
 *   Lower left  quadrant 3: 31 32 33 34 35 36 37 38  (displayed as 1 2 3 4 5 6 7 8)
 *
 * Primary teeth (letters A-E, stored as FDI 5x/6x/7x/8x):
 *   Upper right: 55 54 53 52 51  → E D C B A
 *   Upper left:  61 62 63 64 65  → A B C D E
 *   Lower right: 85 84 83 82 81  → E D C B A
 *   Lower left:  71 72 73 74 75  → A B C D E
 */

$upper_r = [18,17,16,15,14,13,12,11];
$upper_l = [21,22,23,24,25,26,27,28];
$lower_r = [48,47,46,45,44,43,42,41];
$lower_l = [31,32,33,34,35,36,37,38];

// Display labels for permanent teeth: 8→1 on right side, 1→8 on left side
$perm_display = [];
foreach ($upper_r as $i => $t) $perm_display[$t] = 8 - $i;  // 18→8, 17→7 ... 11→1
foreach ($upper_l as $i => $t) $perm_display[$t] = $i + 1;  // 21→1, 22→2 ... 28→8
foreach ($lower_r as $i => $t) $perm_display[$t] = 8 - $i;
foreach ($lower_l as $i => $t) $perm_display[$t] = $i + 1;

// Primary teeth
$primary_ul = ['55','54','53','52','51'];  // upper right → E D C B A
$primary_ur = ['61','62','63','64','65'];  // upper left  → A B C D E
$primary_ll = ['85','84','83','82','81'];  // lower right → E D C B A
$primary_lr = ['71','72','73','74','75'];  // lower left  → A B C D E

$primary_labels = [
  '55'=>'E','54'=>'D','53'=>'C','52'=>'B','51'=>'A',
  '61'=>'A','62'=>'B','63'=>'C','64'=>'D','65'=>'E',
  '85'=>'E','84'=>'D','83'=>'C','82'=>'B','81'=>'A',
  '71'=>'A','72'=>'B','73'=>'C','74'=>'D','75'=>'E',
];

$status_colors = [
  'normal'     => ['bg'=>'#BBF7D0','border'=>'#22C55E','text'=>'#14532D'],
  'caries'     => ['bg'=>'#FDE68A','border'=>'#F59E0B','text'=>'#78350F'],
  'filling'    => ['bg'=>'#BFDBFE','border'=>'#3B82F6','text'=>'#1E3A8A'],
  'extraction' => ['bg'=>'#FECACA','border'=>'#EF4444','text'=>'#7F1D1D'],
  'missing'    => ['bg'=>'#E5E7EB','border'=>'#6B7280','text'=>'#374151'],
  'crown'      => ['bg'=>'#DDD6FE','border'=>'#7C3AED','text'=>'#3B0764'],
  'rootcanal'  => ['bg'=>'#FFEDD5','border'=>'#F97316','text'=>'#7C2D12'],
  'bridge'     => ['bg'=>'#CFFAFE','border'=>'#06B6D4','text'=>'#164E63'],
  'implant'    => ['bg'=>'#FCE7F3','border'=>'#EC4899','text'=>'#831843'],
  'denture'    => ['bg'=>'#FEF3C7','border'=>'#D97706','text'=>'#78350F'],
];

if (!function_exists('tc_cell_style')) {
    function tc_cell_style(string $tooth, array $chart_teeth, array $status_colors, string $mode): string {
        $base = 'display:inline-flex;align-items:center;justify-content:center;width:30px;height:27px;border:1.5px solid #CBD5E1;border-radius:3px;font-size:0.72rem;font-weight:700;font-family:inherit;color:#64748B;background:#F8FAFC;transition:all 0.12s;box-sizing:border-box;';
        if ($mode === 'input') $base .= 'cursor:pointer;';
        if (isset($chart_teeth[$tooth])) {
            $st = $chart_teeth[$tooth];
            if (isset($status_colors[$st])) {
                $c = $status_colors[$st];
                $base .= "background:{$c['bg']};border-color:{$c['border']};color:{$c['text']};";
            }
        }
        return $base;
    }
}

$uid      = htmlspecialchars($chart_uid);
$is_input = ($tc_mode === 'input');
?>
<style>
/* ── Flat Grid Tooth Chart (paper-style 1-8) ─── */
.tc-grid-wrap-<?= $uid ?> { font-family: inherit; user-select: none; }
.tc-row {
    display: flex; align-items: center; justify-content: center;
    gap: 2px; flex-wrap: nowrap;
}
.tc-midline {
    width: 2px; background: #94A3B8; align-self: stretch;
    margin: 0 5px; border-radius: 2px;
}
.tc-arch-label {
    font-size: 0.62rem; font-weight: 700; color: #94A3B8;
    text-align: center; letter-spacing: 0.06em; text-transform: uppercase;
    margin: 4px 0 2px;
}
.tc-gumline {
    width: 100%; height: 2px; background: #CBD5E1; margin: 5px 0; border-radius: 2px;
}
.tc-gumline-wrap {
    display: flex; align-items: center; gap: 6px; margin: 4px 0;
}
.tc-gumline-label {
    font-size: 0.58rem; color: #94A3B8; white-space: nowrap;
    letter-spacing: 0.05em; font-weight: 700;
}
.tc-quadrant-hint {
    display: flex; justify-content: center; gap: 0;
    font-size: 0.58rem; color: #CBD5E1; font-weight: 600;
    letter-spacing: 0.03em; margin-bottom: 1px;
}
.tc-legend {
    display: flex; flex-wrap: wrap; gap: 6px; justify-content: center;
    margin-top: 10px; font-size: 0.67rem;
}
.tc-legend-item {
    display: flex; align-items: center; gap: 4px; color: #64748B;
    cursor: pointer; padding: 2px 6px; border-radius: 4px;
    border: 1px solid transparent;
}
.tc-legend-swatch { width: 12px; height: 12px; border-radius: 2px; flex-shrink: 0; }
[data-theme="dark"] .tc-cell { background:#334155!important;border-color:#475569!important;color:#94A3B8!important; }
[data-theme="dark"] .tc-midline { background:#64748B; }
[data-theme="dark"] .tc-gumline { background:#334155; }
[data-theme="dark"] .tc-arch-label { color:#475569; }
[data-theme="dark"] .tc-gumline-label { color:#334155; }
</style>

<div class="tc-grid-wrap-<?= $uid ?>">

    <!-- Patient orientation hint -->
    <div class="tc-quadrant-hint">
        <span style="flex:1;text-align:right;padding-right:12px;">← patient's right</span>
        <span style="flex:1;text-align:left;padding-left:12px;">patient's left →</span>
    </div>

    <!-- UPPER ARCH LABEL -->
    <div class="tc-arch-label">Upper Arch</div>

    <!-- PRIMARY UPPER ROW: E D C B A | A B C D E -->
    <div class="tc-row" style="margin-bottom:2px;">
        <?php foreach ($primary_ul as $t): ?>
        <span class="tc-cell <?= $is_input ? 'tc-btn' : '' ?>"
              data-tooth="<?= $t ?>"
              data-uid="<?= $uid ?>"
              style="<?= tc_cell_style($t,$chart_teeth,$status_colors,$tc_mode) ?>"
              title="Primary <?= $t ?> (<?= $primary_labels[$t] ?>)">
            <?= $primary_labels[$t] ?>
        </span>
        <?php endforeach; ?>
        <div class="tc-midline" style="height:27px;margin:0 5px;"></div>
        <?php foreach ($primary_ur as $t): ?>
        <span class="tc-cell <?= $is_input ? 'tc-btn' : '' ?>"
              data-tooth="<?= $t ?>"
              data-uid="<?= $uid ?>"
              style="<?= tc_cell_style($t,$chart_teeth,$status_colors,$tc_mode) ?>"
              title="Primary <?= $t ?> (<?= $primary_labels[$t] ?>)">
            <?= $primary_labels[$t] ?>
        </span>
        <?php endforeach; ?>
    </div>

    <!-- PERMANENT UPPER ROW: 8 7 6 5 4 3 2 1 | 1 2 3 4 5 6 7 8 -->
    <div class="tc-row">
        <?php foreach ($upper_r as $t): ?>
        <span class="tc-cell <?= $is_input ? 'tc-btn' : '' ?>"
              data-tooth="<?= $t ?>"
              data-uid="<?= $uid ?>"
              style="<?= tc_cell_style((string)$t,$chart_teeth,$status_colors,$tc_mode) ?>"
              title="Tooth <?= $t ?> (Q1-<?= $perm_display[$t] ?>)">
            <?= $perm_display[$t] ?>
        </span>
        <?php endforeach; ?>
        <div class="tc-midline" style="height:27px;margin:0 5px;"></div>
        <?php foreach ($upper_l as $t): ?>
        <span class="tc-cell <?= $is_input ? 'tc-btn' : '' ?>"
              data-tooth="<?= $t ?>"
              data-uid="<?= $uid ?>"
              style="<?= tc_cell_style((string)$t,$chart_teeth,$status_colors,$tc_mode) ?>"
              title="Tooth <?= $t ?> (Q2-<?= $perm_display[$t] ?>)">
            <?= $perm_display[$t] ?>
        </span>
        <?php endforeach; ?>
    </div>

    <!-- GUM LINE -->
    <div class="tc-gumline-wrap">
        <div class="tc-gumline"></div>
        <span class="tc-gumline-label">GUM LINE</span>
        <div class="tc-gumline"></div>
    </div>

    <!-- PERMANENT LOWER ROW: 8 7 6 5 4 3 2 1 | 1 2 3 4 5 6 7 8 -->
    <div class="tc-row">
        <?php foreach ($lower_r as $t): ?>
        <span class="tc-cell <?= $is_input ? 'tc-btn' : '' ?>"
              data-tooth="<?= $t ?>"
              data-uid="<?= $uid ?>"
              style="<?= tc_cell_style((string)$t,$chart_teeth,$status_colors,$tc_mode) ?>"
              title="Tooth <?= $t ?> (Q4-<?= $perm_display[$t] ?>)">
            <?= $perm_display[$t] ?>
        </span>
        <?php endforeach; ?>
        <div class="tc-midline" style="height:27px;margin:0 5px;"></div>
        <?php foreach ($lower_l as $t): ?>
        <span class="tc-cell <?= $is_input ? 'tc-btn' : '' ?>"
              data-tooth="<?= $t ?>"
              data-uid="<?= $uid ?>"
              style="<?= tc_cell_style((string)$t,$chart_teeth,$status_colors,$tc_mode) ?>"
              title="Tooth <?= $t ?> (Q3-<?= $perm_display[$t] ?>)">
            <?= $perm_display[$t] ?>
        </span>
        <?php endforeach; ?>
    </div>

    <!-- PRIMARY LOWER ROW: E D C B A | A B C D E -->
    <div class="tc-row" style="margin-top:2px;">
        <?php foreach ($primary_ll as $t): ?>
        <span class="tc-cell <?= $is_input ? 'tc-btn' : '' ?>"
              data-tooth="<?= $t ?>"
              data-uid="<?= $uid ?>"
              style="<?= tc_cell_style($t,$chart_teeth,$status_colors,$tc_mode) ?>"
              title="Primary <?= $t ?> (<?= $primary_labels[$t] ?>)">
            <?= $primary_labels[$t] ?>
        </span>
        <?php endforeach; ?>
        <div class="tc-midline" style="height:27px;margin:0 5px;"></div>
        <?php foreach ($primary_lr as $t): ?>
        <span class="tc-cell <?= $is_input ? 'tc-btn' : '' ?>"
              data-tooth="<?= $t ?>"
              data-uid="<?= $uid ?>"
              style="<?= tc_cell_style($t,$chart_teeth,$status_colors,$tc_mode) ?>"
              title="Primary <?= $t ?> (<?= $primary_labels[$t] ?>)">
            <?= $primary_labels[$t] ?>
        </span>
        <?php endforeach; ?>
    </div>

    <!-- LOWER ARCH LABEL -->
    <div class="tc-arch-label" style="margin-top:4px;">Lower Arch</div>

    <?php if ($is_input): ?>
    <!-- LEGEND (input mode only) -->
    <div class="tc-legend" id="tcLegend_<?= $uid ?>">
        <?php foreach ($status_colors as $st => $c): ?>
        <div class="tc-legend-item" data-status="<?= $st ?>" data-uid="<?= $uid ?>">
            <span class="tc-legend-swatch" style="background:<?= $c['bg'] ?>;border:1px solid <?= $c['border'] ?>;"></span>
            <?= ucfirst($st === 'rootcanal' ? 'Root Canal' : $st) ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Selected display -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:10px;flex-wrap:wrap;gap:6px;">
        <span id="tcDisplay_<?= $uid ?>" style="font-size:0.78rem;color:var(--gray-500);">No teeth selected — click to tag</span>
        <button type="button" onclick="tcClear('<?= $uid ?>')"
                style="font-size:0.72rem;padding:3px 10px;background:none;border:1px solid var(--gray-300);border-radius:6px;cursor:pointer;color:var(--gray-500);">
            ✕ Clear
        </button>
    </div>
    <?php endif; ?>

</div><!-- /tc-grid-wrap -->

<?php if ($is_input): ?>
<script>
(function(){
    var uid      = <?= json_encode($uid) ?>;
    var inputSel = <?= json_encode('[name="' . $tc_input_name . '"]') ?>;
    var selId    = <?= json_encode($ts_select_id) ?>;
    var initial  = <?= json_encode($tc_initial) ?>;

    var selected = new Set();
    var STATUS_COLORS = <?= json_encode($status_colors) ?>;

    function cellStyle(active, status) {
        if (!active) return 'background:#F8FAFC;border-color:#CBD5E1;color:#64748B;';
        var c = STATUS_COLORS[status];
        if (!c) c = {bg:'#DBEAFE',border:'#3B82F6',text:'#1E3A8A'};
        return 'background:'+c.bg+';border-color:'+c.border+';color:'+c.text+';';
    }

    function getStatus() {
        var s = document.getElementById(selId);
        return s ? s.value : 'normal';
    }

    function repaintAll() {
        var st = getStatus();
        // Resolve the colour set once per repaint so every tooth uses the same status
        var c = STATUS_COLORS[st] || {bg:'#DBEAFE', border:'#3B82F6', text:'#1E3A8A'};
        document.querySelectorAll('.tc-btn[data-uid="'+uid+'"]').forEach(function(el){
            var t = el.dataset.tooth;
            var isSelected = selected.has(t);
            // ── Layout-only cssText (no color/background/border here) ──────────
            // Mobile browsers (Safari/Chrome Android) are inconsistent when
            // cssText contains the same property twice — the colour from
            // cellStyle() was being silently dropped. Setting colour properties
            // individually AFTER cssText guarantees they always win.
            el.style.cssText = 'display:inline-flex;align-items:center;justify-content:center;'
                             + 'width:30px;height:27px;border-radius:3px;font-size:0.72rem;'
                             + 'font-weight:700;font-family:inherit;transition:all 0.12s;'
                             + 'box-sizing:border-box;cursor:pointer;';
            // ── Color properties set individually ─────────────────────────────
            el.style.background  = isSelected ? c.bg     : '#F8FAFC';
            el.style.border      = '1.5px solid ' + (isSelected ? c.border : '#CBD5E1');
            el.style.color       = isSelected ? c.text   : '#64748B';
        });
        updateDisplay();
        updateLegend();
    }

    function updateDisplay() {
        var el = document.getElementById('tcDisplay_'+uid);
        if (!el) return;
        if (selected.size === 0) {
            el.textContent = 'No teeth selected — click to tag';
        } else {
            var nums = Array.from(selected).sort(function(a,b){ return parseInt(a)-parseInt(b)||a.localeCompare(b); });
            el.innerHTML = '<strong style="color:var(--blue-600);">'+(selected.size > 1 ? selected.size+' teeth' : '1 tooth')+':</strong> '+
                nums.map(function(t){ return '<span style="display:inline-block;background:#EFF6FF;border:1px solid #BFDBFE;border-radius:3px;padding:1px 4px;font-size:0.72rem;margin:1px;">'+t+'</span>'; }).join(' ');
        }
    }

    function syncInput() {
        var nums = Array.from(selected).sort(function(a,b){ return parseInt(a)-parseInt(b)||a.localeCompare(b); });
        var inp = document.querySelector(inputSel);
        if (inp) inp.value = nums.join(', ');
        updateDisplay();
    }

    function updateLegend() {
        var st = getStatus();
        var legend = document.getElementById('tcLegend_'+uid);
        if (!legend) return;
        legend.querySelectorAll('.tc-legend-item').forEach(function(el){
            var s = el.dataset.status;
            var c = STATUS_COLORS[s];
            if (s === st) {
                el.style.borderColor = c ? c.border : '#3B82F6';
                el.style.background  = c ? c.bg+'44' : '';
            } else {
                el.style.borderColor = 'transparent';
                el.style.background  = '';
            }
        });
    }

    document.querySelectorAll('.tc-btn[data-uid="'+uid+'"]').forEach(function(el){
        el.addEventListener('click', function(){
            var t = this.dataset.tooth;
            if (selected.has(t)) selected.delete(t); else selected.add(t);
            repaintAll();
            syncInput();
        });
    });

    var statusSelect = document.getElementById(selId);
    if (statusSelect) statusSelect.addEventListener('change', function(){ repaintAll(); });

    var legend = document.getElementById('tcLegend_'+uid);
    if (legend) {
        legend.querySelectorAll('.tc-legend-item').forEach(function(el){
            el.addEventListener('click', function(){
                var st = this.dataset.status;
                var sel = document.getElementById(selId);
                if (sel) { sel.value = st; sel.dispatchEvent(new Event('change')); }
            });
        });
    }

    var inp = document.querySelector(inputSel);
    if (inp) {
        inp.addEventListener('input', function(){
            var parts = this.value.split(/[\s,;]+/).map(function(s){ return s.trim(); }).filter(Boolean);
            selected = new Set(parts);
            repaintAll();
        });
    }

    if (initial) {
        initial.split(/[\s,;]+/).forEach(function(t){ if(t.trim()) selected.add(t.trim()); });
        repaintAll();
        syncInput();
    }

    updateLegend();

    window.tcClear = function(u) {
        if (u !== uid) return;
        selected.clear();
        var inp2 = document.querySelector(inputSel);
        if (inp2) inp2.value = '';
        repaintAll();
    };
})();
</script>
<?php endif; ?>