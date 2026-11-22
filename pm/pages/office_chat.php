<?php /** 即時溝通 */ ?>
<div class="easyui-layout pm-chat" fit="true" border="false" id="<?php echo $uid; ?>_box">
    <div region="west" split="true" title="聯繫人" style="width:220px;">
        <ul class="pm-chat-users" id="<?php echo $uid; ?>_users"></ul>
    </div>
    <div region="center" title="公共群聊" id="<?php echo $uid; ?>_center" style="overflow:hidden;">
        <div class="pm-chat-msgs" id="<?php echo $uid; ?>_msgs"></div>
        <div class="pm-chat-input">
            <textarea id="<?php echo $uid; ?>_text" placeholder="輸入消息，按 Enter 發送，Shift+Enter 換行" maxlength="1000"></textarea>
            <a class="easyui-linkbutton" icon="icon-ok" id="<?php echo $uid; ?>_send">發送</a>
        </div>
    </div>
</div>
<script type="text/javascript">
$(function(){
    var U = '#<?php echo $uid; ?>', me = <?php echo (int)$user['id']; ?>, peer = 0, last = 0, busy = false;
    var $msgs = $(U + '_msgs'), $users = $(U + '_users'), $text = $(U + '_text');
    function loadUsers() {
        $.ajax({url: 'api/chat.php', data: {act: 'users'}, dataType: 'json', global: false}).done(function(res){
            if (!res || !res.ok) return;
            var list = [{id: 0, name: '公共群聊', role: '全體成員', unread: res.pub_unread, online: true}].concat(res.users);
            $users.empty();
            $.each(list, function(i, u){
                var $li = $('<li/>').toggleClass('on', u.id === peer).appendTo($users);
                $('<i/>').addClass(u.online ? 'online' : '').appendTo($li);
                $('<span class="n"/>').text(u.name).appendTo($li);
                $('<span class="r"/>').text(u.role || '').appendTo($li);
                if (u.unread && u.id !== peer) $('<b/>').text(u.unread).appendTo($li);
                $li.click(function(){ peer = u.id; last = 0; $msgs.empty(); $(U + '_box').layout('panel', 'center').panel('setTitle', PM.esc(u.name)); loadUsers(); loadMsgs(); });
            });
        });
    }
    function loadMsgs() {
        if (busy) return;
        busy = true;
        var p = peer;
        $.ajax({url: 'api/chat.php', data: {act: 'msgs', peer: p, after: last}, dataType: 'json', global: false}).done(function(res){
            if (!res || !res.ok || p !== peer) return;
            $.each(res.rows, function(i, m){
                if (m.id <= last) return;
                last = m.id;
                var mine = m.from_id == me, $m = $('<div class="pm-chat-msg"/>').toggleClass('mine', mine).appendTo($msgs);
                $('<div class="h"/>').text((mine ? '我' : m.from_name) + '  ' + m.created_at).appendTo($m);
                $('<div class="c"/>').text(m.content).appendTo($m);
            });
            if (res.rows.length) { $msgs.scrollTop($msgs[0].scrollHeight); PM.ping(); }
        }).always(function(){ busy = false; });
    }
    function send() {
        var v = $.trim($text.val());
        if (!v) return;
        $text.val('');
        $.ajax({url: 'api/chat.php?act=send', type: 'POST', data: {peer: peer, content: v}, dataType: 'json'}).done(function(res){
            if (res && res.ok) loadMsgs(); else PM.alert(res && res.msg ? res.msg : '發送失敗');
        });
    }
    $(U + '_send').click(send);
    $text.keydown(function(e){ if (e.keyCode === 13 && !e.shiftKey) { e.preventDefault(); send(); } });
    loadUsers(); loadMsgs();
    var n = 0, timer = setInterval(function(){
        if (!$(U + '_msgs').length) return clearInterval(timer);
        loadMsgs();
        if (++n % 5 === 0) loadUsers();
    }, 3000);
});
</script>
