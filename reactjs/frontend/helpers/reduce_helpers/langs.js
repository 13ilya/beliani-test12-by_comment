function getCookie(name) {     //get cookies from browser
  var matches = document.cookie.match(new RegExp(
    '(?:^|; )' + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + '=([^;]*)'
  ));
  return matches ? decodeURIComponent(matches[1]) : undefined;
}

function setCookie(name, value) {
    let date = new Date(new Date().getTime() + 60 * 1000000000);
    value = encodeURIComponent(value);
    document.cookie = name+'='+value+'; path=/; expires=' + date.toUTCString();
}
function lang(state, action) {
    switch (action.type) {
        case 'TOGGLE_LANG':
            if(state.id !== action.id){
                return state;
            }
            return {
                ...state,
                isActive: !state.isActive
            };
        default:
            return state
    }
}


function langs(state = [], action) {
    let langsArray,
        langsActive,
        main_lang_react;
    switch (action.type){
        case 'FETCH_LANGS':
            langsArray = action.langs;
            var defVal=true;
            langsActive = getCookie('langSetting');
            main_lang_react = getCookie('main_lang_react');

            if (langsActive!=undefined){
                langsActive=langsActive.split(',');
                defVal=false;
            }
            else langsActive=''
            let newLangsArray = langsArray.map( (cur,idx)=>{
                var arrKeys = Object.keys(cur);
                if (defVal)langsActive+='1,';
                return {
                    id: idx,
                    title: arrKeys[0],
                    alias: cur[arrKeys[0]],
                    main: main_lang_react == idx ? true : false,
                    isActive: defVal?true:Number(langsActive[idx])
                }
            });
            if(defVal) setCookie('langSetting',langsActive);
            return newLangsArray;
        case 'TOGGLE_LANG':
            langsActive=getCookie('langSetting');
            langsActive=langsActive.split(',');
            langsActive[action.id]=(Number(langsActive[action.id])>0)?0:1;
            langsActive=langsActive.join(',');
            setCookie('langSetting',langsActive);
            return state.map( l => lang(l, action) );
        case 'CHANGE_MAIN_LANG':
            setCookie('main_lang_react',action.id);
            let clone = state.map((item) => {return item});
            var active = _.find(clone,{main:true});
            if(active) active.main = false;
            clone[action.id].main = true;
            return clone;
        default:
            return state
    }
}

export default langs
