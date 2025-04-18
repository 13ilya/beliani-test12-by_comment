import React from 'react'
import MulangField from './MulangField'

export default class mulangFields extends React.Component {
    constructor(props) {
        super(props);
    }

    render(){
        const mulangFieldsTitle = this.props.mulangFieldsTitle || '',
              onChangeMulangField = this.props.onChangeMulangField,
              onSave = this.props.onSave,
              langs = this.props.langs || {},
              name = this.props.name || '',
              type = this.props.type || '',
              layout = this.props.layout || '', //set tabs for tabs layout
              collapce = this.props.collapce || false,
              FormClassSet = this.props.FormClassSet || '',//custom class for multi field
              checkShow = this.props.checkShow || false,
              onUpdateMulang = this.props.onUpdateMulang,//function for button update
              counting = this.props.counting || false, //show letter counting
              limit = this.props.limit || 0,//limit for chars
              hideParam = this.props.hideParam || false; //hide [[param]]

        let   MulangsData = this.props.mulangFieldsArr  || {},
              langsTemp={},
              langsTitle=[],
              lowlangName='',
              langName='',
              len=0;

        langs.forEach((item) => {
            lowlangName = item.title.toLowerCase();
            langName = item.title;
            if((MulangsData[langName]!=undefined)&&(item.isActive)){
              langsTemp[lowlangName]=MulangsData[langName];
              langsTemp[lowlangName]['langName']=langName;
              langsTitle.push(lowlangName);
            }
        });
        let mulangFieldsArr=langsTemp;
        if(langsTitle.length === 0) return(<div></div>);
        const mulangFieldsKeys = langsTitle.sort();
        const MulangFieldCol = mulangFieldsKeys.map((item,idx) => {
            if(mulangFieldsArr[item] === undefined){
                return false;
            }
            return (
                <MulangField
                    title_lang={mulangFieldsArr[item].langName}
                    key={idx}
                    className={FormClassSet}
                    onChangeMulangField = {onChangeMulangField}
                    onUpdateMulang={onUpdateMulang}
                    type={type}
                    name={name}
                    hideParam={hideParam}
                    {...mulangFieldsArr[item]}
                    checkShow={checkShow}
                 />
            )
        });
        const buttonSave = ()=> {
            if (onSave === undefined)return (<div></div>);
            else {
                return (
                    <div className='form-group col-md-12'>
                        <button
                            onClick={(e)=> {e.preventDefault();onSave();}}
                            type='submit'
                            className='btn btn-default'
                        >
                            Save
                        </button>
                    </div>
                );
            }
        };
        if(layout == 'tabs'){
            let mulangFieldsTitleFormat = mulangFieldsTitle.replace(/\s+/g, '').replace(/[\])}[{(]/g, '');
            let first=-1
            return(
                <div style={{overflow:'auto'}}>
                    <ul className='nav nav-tabs' role='tablist'>
                        {mulangFieldsKeys.map((item,idx) => {
                            if(mulangFieldsArr[item] === undefined){
                                return false;
                            }
                            else if(first==-1)first=idx;

                            let itemVal=mulangFieldsArr[item].value ? mulangFieldsArr[item].value.length:0;
                            if (counting) len = limit - itemVal // show limit of chars  in tabs header
                            return (
                                <li role='presentation' key={idx} className={idx == first ? 'active':''}>
                                    <a href={'#edit'+item+'_'+mulangFieldsTitleFormat}
                                    aria-controls='edit_saved_auction'
                                    role='tab'
                                    data-toggle='tab'
                                    name={'#edit'+item+'_'}
                                    onClick={(e)=>{
                                      $('a[name="'+e.target.name+'"]').click();
                                    }}
                                    >{counting?item+':('+len+')':item}</a>
                                </li>
                            )
                        })}
                    </ul>
                    <div className='tab-content'>
                        {mulangFieldsKeys.map((item,idx) => {
                            if(mulangFieldsArr[item] === undefined){
                                return false;
                            }
                            else if(first==-1)first=idx;
                            return (
                                <div role='tabpanel' key={idx} className={idx == first ? 'tab-pane active':'tab-pane'} id={'edit'+item+'_'+mulangFieldsTitleFormat}>
                                    <br/>
                                    <MulangField
                                        title_lang={mulangFieldsArr[item].langName}
                                        key={idx}
                                        onChangeMulangField={onChangeMulangField}
                                        onUpdateMulang={onUpdateMulang}
                                        type={type}
                                        name={name}
                                        hideParam={hideParam}
                                        className='form-group col-md-12'
                                        {...mulangFieldsArr[item]}
                                        tab={true}
                                        counting={counting}
                                        limit={limit}
                                        checkShow={checkShow}
                                    />

                                </div>
                            )
                        })}
                        {buttonSave()}
                    </div>
                </div>
            )
        } else {
            if(!collapce)
            return (
                <div>
                    <form>
                        {MulangFieldCol}
                        {buttonSave()}
                    </form>
                </div>
            )
            else
          return (
                <div>
                    <form>
                        {MulangFieldCol}
                        {buttonSave()}
                    </form>
                </div>
            )
        }
    }
}
