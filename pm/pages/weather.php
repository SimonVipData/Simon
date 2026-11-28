<?php
/** 工程輔助 - 天氣預測：澳門天氣 4 / 6 / 8 分屏牆（每個分屏可選來源；用戶設置自動保存，管理員可設定默認） */
?>
<style>
.wx-wall{position:absolute;top:0;left:0;right:0;bottom:0;display:flex;flex-direction:column;background:#eef3f8;}
.wx-wall:fullscreen{background:#000;}
.wx-tools{flex:none;padding:4px 8px;border-bottom:1px solid #c5d5e6;background:#f4f8fc;line-height:24px;}
.wx-tools .wx-lay{display:inline-block;padding:0 10px;margin-right:2px;border:1px solid #9fb8d3;border-radius:3px;background:#fff;color:#1F5FA8;cursor:pointer;}
.wx-tools .wx-lay.wx-on{background:#1F5FA8;color:#fff;border-color:#1F5FA8;}
.wx-tools .pm-note{margin-left:10px;color:#888;}
.wx-grid{flex:1;display:grid;gap:4px;padding:4px;position:relative;min-height:0;}
.wx-l4{grid-template-columns:repeat(2,1fr);grid-template-rows:repeat(2,1fr);}
.wx-l6{grid-template-columns:repeat(3,1fr);grid-template-rows:repeat(2,1fr);}
.wx-l8{grid-template-columns:repeat(4,1fr);grid-template-rows:repeat(2,1fr);}
.wx-pane{position:relative;display:flex;flex-direction:column;min-width:0;min-height:0;background:#fff;border:1px solid #99bbe8;}
.wx-pane.wx-full{position:absolute;top:4px;left:4px;right:4px;bottom:4px;z-index:5;}
.wx-hd{flex:none;height:24px;line-height:24px;padding:0 4px;background:linear-gradient(#f5f9fd,#dae7f6);border-bottom:1px solid #99bbe8;white-space:nowrap;overflow:hidden;display:flex;}
.wx-no{flex:none;display:inline-block;width:18px;height:18px;line-height:18px;margin:3px 4px 0 0;border-radius:9px;background:#1F5FA8;color:#fff;text-align:center;font-size:11px;}
.wx-t{flex:1;font-weight:bold;color:#15428b;overflow:hidden;text-overflow:ellipsis;}
.wx-ops{flex:none;}
.wx-ops a{margin-left:6px;color:#1F5FA8;}
.wx-ops select.wx-cam{height:20px;line-height:20px;padding:0;margin:0;width:150px;font-size:12px;}
.wx-bd{flex:1;position:relative;overflow:hidden;background:#000;}
.wx-pane[data-k=iframe] .wx-bd{background:#fff;}
.wx-bd iframe,.wx-bd video{position:absolute;top:0;left:0;width:100%;height:100%;border:0;}
.wx-bd img{position:absolute;top:0;left:0;width:100%;height:100%;object-fit:contain;cursor:pointer;}
.wx-cap{position:absolute;left:0;right:0;bottom:0;padding:1px 6px;background:rgba(0,0,0,.55);color:#fff;font-size:12px;}
.wx-msg{position:absolute;top:40%;left:0;right:0;text-align:center;color:#fff;background:rgba(0,0,0,.6);padding:8px;}
.wx-pane[data-k=iframe] .wx-msg{color:#333;background:none;}
.wx-st{position:absolute;right:4px;bottom:2px;font-size:11px;color:#888;pointer-events:none;}
.wx-pane[data-k=img] .wx-st,.wx-pane[data-k=hls] .wx-st{bottom:20px;color:#ddd;}
</style>
<div class="wx-wall" id="<?php echo $uid; ?>_wall">
    <div class="wx-tools">
        <span class="wx-lay" data-n="4">4 分屏</span><span class="wx-lay" data-n="6">6 分屏</span><span class="wx-lay" data-n="8">8 分屏</span>
        <a class="easyui-linkbutton" icon="icon-reload" plain="true" id="<?php echo $uid; ?>_reload">全部刷新</a>
        <a class="easyui-linkbutton" icon="icon-search" plain="true" id="<?php echo $uid; ?>_fs">全屏顯示</a>
        <a class="easyui-linkbutton" icon="icon-undo" plain="true" id="<?php echo $uid; ?>_reset">恢復默認</a>
        <?php if ($admin): ?>
        <a class="easyui-linkbutton" icon="icon-save" plain="true" id="<?php echo $uid; ?>_setdef">設為所有用戶默認</a>
        <a class="easyui-linkbutton" icon="icon-cancel" plain="true" id="<?php echo $uid; ?>_resetdef">清除管理員默認</a>
        <?php endif; ?>
        <span class="pm-note">每個分屏可點「設置」更換來源；設置自動保存。網頁無法顯示時請點「新窗口」。</span>
    </div>
    <div class="wx-grid wx-l4" id="<?php echo $uid; ?>_grid"></div>
</div>
<script type="text/javascript" charset="utf-8" src="resource/js/pm.weather.js?v=<?php echo @filemtime(__DIR__ . '/../resource/js/pm.weather.js'); ?>"></script>
<script type="text/javascript">
$(function(){ PMWeather.init('#<?php echo $uid; ?>'); });
</script>
