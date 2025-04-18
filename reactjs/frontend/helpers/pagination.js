//clearCache.jsx
import React from 'react'
import PureRenderMixin from 'react-addons-pure-render-mixin'

export default React.createClass({
  mixins:[PureRenderMixin],
  componentWillMount: function() {
      this.setState({pagSet:1})
  },
  changePagSet:  function (param){
      console.log(this,param);
      this.setState({pagSet:param})
  },
  render(){
    const pagCount = this.props.pagCount?this.props.pagCount:0;
    const pagDefault = this.props.pagDefault?this.props.pagDefault:1;
    const pagSet = this.state['pagSet'];
    const changePagSet = this.changePagSet;
    const pagFunc = this.props.pagFunc?this.props.pagFunc:null;
    const bsClass = this.props.bsClass?this.props.bsClass:'';
    const showPag = this.props.showPag?this.props.showPag:5;
    if(pagCount<101)return(<div></div>)
    const sets = (pagCount/100|0+(pagCount%100?1:0))
    let fullSets = sets/showPag|0
    let modSet = sets%showPag
    let maxSteps = fullSets+(modSet?1:0);
    let arr=[]
    let start_step=(pagSet-1)*showPag?(pagSet-1)*showPag:1;
    let offset=pagSet>fullSets && modSet?Number(start_step+modSet):Number(start_step+showPag);
    for(let i=start_step; i<offset;i++){
        arr.push(<li key={i} className={i==pagDefault?'active':''}><a id={i} onClick={(e)=>{pagFunc(e.target.id);return false;}}>{i}</a></li>)
    }
    return(
      <div className={bsClass}>
        <ul className='pagination'>
          <li className={pagSet==1?'disabled':''} >
              <a onClick={()=>{pagSet!=1?changePagSet(pagSet-1):false;return false;}}>&laquo;</a>
          </li>
          {arr}
          <li className={pagSet==maxSteps?'disabled':''}>
              <a onClick={()=>{pagSet!=maxSteps?changePagSet(pagSet+1):false;return false;}}>&raquo;</a>
          </li>
        </ul>
      </div>
    )
  }
})
