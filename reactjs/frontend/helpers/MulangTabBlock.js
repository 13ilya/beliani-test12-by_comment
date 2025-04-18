import React, { Component } from 'react'
import Panel from './Panel'

class MulangTabBlock extends Component{
	render (){
	const {
		value,
		langs,
		tabTitle,
		}=this.props.options;


	let activeLangs = {};
    langs.forEach( (item) => {
        activeLangs[item.title.toLowerCase()] = item.isActive;
    });

    const mulangFieldsKeys = Object.keys(value).sort();
	let tabTitleFormat = tabTitle.replace(/\s+/g, '').replace(/[\])}[{(]/g, '');
       return(
         <div>
                <ul className='nav nav-tabs' role='tablist'>
                    {mulangFieldsKeys.map((item,idx) => {
                        if(!activeLangs[item.toLowerCase()] || value[item] === undefined){
                            return false;
                        }
                        return (
                            <li role='presentation' key={idx} className={idx == 0 ? 'active':''}>
                                <a href={'#edit'+item+'_'+tabTitleFormat} aria-controls='edit_saved_auction' role='tab' data-toggle='tab'>{item.toLowerCase()}</a>
                            </li>
                        )
                    })}
                </ul>
                <div className='tab-content'>
                    {mulangFieldsKeys.map((item,idx) => {
                        if(!activeLangs[item.toLowerCase()] || value[item] === undefined){
                            return false;
                        }
                        return (
                            <div role='tabpanel' key={idx} className={idx == 0 ? 'tab-pane active':'tab-pane'} id={'edit'+item+'_'+tabTitleFormat}>
                                <Panel>
									{value[item]}
                                </Panel>
                            </div>
                        )
                    })}
                </div>
            </div>
        )
}
}
export default MulangTabBlock
