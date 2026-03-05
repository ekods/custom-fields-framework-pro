(function(wp){
  if (!wp || !wp.plugins || !wp.editPost || !wp.element || !wp.domReady) return;
  if (!window.CFFBlockSidebar || !window.CFFBlockSidebar.enabled) return;

  var registerPlugin = wp.plugins.registerPlugin;
  var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
  var createElement = wp.element.createElement;
  var useEffect = wp.element.useEffect;
  var useState = wp.element.useState;
  var Fragment = wp.element.Fragment;

  function scanGroups(){
    var groups = [];
    var nodes = document.querySelectorAll('.edit-post-meta-boxes-area .postbox[id^="cff_group_"]');
    nodes.forEach(function(node){
      var id = node.id || '';
      if (!id) return;
      var titleNode = node.querySelector('.hndle, .postbox-header .hndle, .postbox-header h2');
      var title = (titleNode && titleNode.textContent) ? titleNode.textContent.trim() : id;
      groups.push({ id: id, title: title });
    });
    return groups;
  }

  function relocateGroup(groupId){
    var source = document.getElementById(groupId);
    var target = document.getElementById('cff-sidebar-target-' + groupId);
    if (!source || !target) return;

    var inside = source.querySelector('.inside');
    if (!inside) return;
    if (target.contains(inside)) return;

    target.innerHTML = '';
    target.appendChild(inside);
    source.classList.add('cff-sidebar-source-hidden');
  }

  function SidebarPanels(){
    var _useState = useState([]);
    var groups = _useState[0];
    var setGroups = _useState[1];

    useEffect(function(){
      function refresh(){
        setGroups(scanGroups());
      }
      refresh();
      var timer = setInterval(refresh, 1500);
      return function(){
        clearInterval(timer);
      };
    }, []);

    useEffect(function(){
      groups.forEach(function(group){
        relocateGroup(group.id);
      });
    }, [groups]);

    if (!groups.length) {
      return createElement(
        PluginDocumentSettingPanel,
        { name: 'cff-fields-empty', title: (window.CFFBlockSidebar && window.CFFBlockSidebar.title) || 'CFF Fields' },
        createElement('p', null, (window.CFFBlockSidebar && window.CFFBlockSidebar.empty) || 'No CFF field groups are active for this post.')
      );
    }

    return createElement(
      Fragment,
      null,
      groups.map(function(group){
        return createElement(
          PluginDocumentSettingPanel,
          { name: 'cff-fields-' + group.id, title: group.title, key: group.id },
          createElement('div', { id: 'cff-sidebar-target-' + group.id, className: 'cff-sidebar-target' })
        );
      })
    );
  }

  registerPlugin('cff-block-sidebar-panels', {
    render: SidebarPanels
  });

  wp.domReady(function(){
    scanGroups().forEach(function(group){
      relocateGroup(group.id);
    });
  });
})(window.wp);
