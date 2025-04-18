import React from 'react'
import ParamLink from '../paramLink'

export default class mulangField extends React.Component {
    constructor(props) {
        super(props);
    }

    render(){
        const {
            title_lang = '',
            value = '',
            last_by = '',
            last_on = '',
            type = '',
            name = '',
            className = '',
            tab = '',
            checkShow = '',
            unchecked = '',
            limit = 0,
            counting = false,
            hideParam = false,
            onChangeMulangField,
            onUpdateMulang,
        } = this.props
        const uncheck = unchecked == '0' && checkShow;
        const showParam = !hideParam?
            <ParamLink  text={'[['+name+'_'+title_lang+']]'}
                        style={
                            {float:'left',
                             position:'relative',
                             zIndex:'2',
                             paddingLeft:'5px',
                             margin:'0px'
                        }}
                        asIcon={false}/>:'';
        let input,oldValue = value;
        let inputHtml = function(){
            switch (type){
                case 'textarea':
                    return (
                        <textarea style={uncheck?{background:'#FFDDDD'}:{}}
                            ref={node => {input = node}}
                            onChange={() =>{
                                    let val = input.value;
                                    if (counting)
                                        val = val.length > limit ? val.substring(0,limit) : val
                                    onChangeMulangField.apply(null, [title_lang, val, name, oldValue]);
                            }}
                            className='form-control'
                            value={value}
                        />
                    );
                default:
                    return(
                        <input style={uncheck?{background:'#FFDDDD'}:{}}
                            ref={node => {input = node}}
                            onChange={() =>{
                                let val = input.value;
                                if (counting)
                                    val = val.length > limit ? val.substring(0,limit) : val
                                onChangeMulangField.apply(null, [title_lang, val, name, oldValue]);
                            }}
                            type='text'
                            name = {name.toLowerCase()+'_'+title_lang}
                            className='form-control'
                            value={value}
                        />
                    );
            }
        };
        return(
            <div className={className ? className : 'form-group col-md-4 muLangCustom'}>
                {!tab?
                    <div>
                        <label htmlFor='exampleInputName2' style={{float:'left',position:'relative',zIndex:'2'}}>{title_lang}: </label>
                        {showParam}
                    </div>
                :showParam}
                {uncheck?
                    <button className='btn btn-danger'
                            onClick={()=>{onUpdateMulang(title_lang,'unchecked','1')}}
                            style={{padding: '2px',float:'right'}}
                    >Checked</button>:''}
                <div className='clearfix'></div>
                {inputHtml()}
                {last_by|| last_on?<p className='small'>Was changed by {last_by} on {last_on}</p>:'' }
            </div>
        )
    }
}
