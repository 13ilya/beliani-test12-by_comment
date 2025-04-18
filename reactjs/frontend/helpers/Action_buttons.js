import React from 'react';

export default class Buttons extends React.Component {
  constructor(props) {
    super(props);
  }

  render() {
      const buttons = this.props.buttons || []
    return (
        <div className='btn-group'>
            {buttons.map((item,idx)=>{
                return <Button_item key = {idx} item = {item}/>
            })}
        </div>
    );
  }
}

export class Button_item extends React.Component {
  constructor(props) {
    super(props);
  }

  render() {
      const {
        title = '',
        click,
        bsClass = '',
        disabled = false
      } = this.props.item
    return (
        <button
            onClick = {click}
            disabled = {disabled}
            className = {'def_btn action_buttons ' + bsClass}>{title}</button>);
  }
}
