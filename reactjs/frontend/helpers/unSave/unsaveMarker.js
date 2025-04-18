import React,{Component} from 'react'

class unSaveMarker extends Component{
    render() {
        const unSaved = this.props.unSaved || {};

    let Names=Object.keys(unSaved);
    if (Names.length==0)return false;
    function getBody(block){
            return block.map((value,key)=>{
                    return(<li key={key}><a data-id={value} href={'#'+value}
                    onClick={(e)=>{
                        let id='#'+e.target.attributes['data-id'].value
                        let parent=$(id).parents('.panel-collapse.collapse').prev().find('a');
                        if(parent!=undefined)
                            if($(parent).attr('aria-expanded')=='false')$(parent).click();
                        console.log('parent',parent);
                    }}
                    >{value}</a></li>)
                })
            }
        return (
            <div className='b-dropdown-unSaved'>
                <div className='btn-group' id='unSavedInner'>
                    <button
                        type='button'
                        className='dropdown-toggle btn btn-danger'
                        onClick={()=>{
                                $('#unSavedInner').toggleClass('open');
                                }}
                        >
                        <span>You have not saved data</span>
                        <b className='caret'>{''}</b>
                    </button>
                    <ul className='dropdown-menu pull-right'>
                        {getBody(Names)}
                    </ul>
                </div>
            </div>
    )
    }
    componentWillMount(){
        this.props.ClearUnsaved();
    }
}

export default unSaveMarker
