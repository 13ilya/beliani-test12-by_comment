import React from 'react';

export default class Desctiption3in1_grid extends React.Component {
    constructor(props) {
        super(props);
    }

    render() {
        let row = [].concat(this.props.children),
            tmp = [],
            resArray = [];
        row.forEach((item,idx)=>{
            tmp.push(item);
            if(tmp.length%3 == 0){
                resArray.push(tmp);
                tmp = [];
            }
        })
        if(tmp.length){
            resArray.push(tmp)    
        }
        return (
            <div >
                {resArray.map((item,idx)=>{
                    return (
                        <div className='row' key = {idx}>
                            {item}
                        </div>
                    )
                })}
            </div>
        );
    }
}
