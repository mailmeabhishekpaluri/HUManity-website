function HC27Section({kind, markup, details}) {
  const dialog = N.useRef(null);
  const opener = N.useRef(null);
  N.useEffect(() => () => {
    if (dialog.current?.open) dialog.current.close();
  }, []);
  function openDetails(event) {
    const button = event.target.closest?.('[data-hc27-open]');
    if (button) {
      opener.current = button;
      dialog.current.showModal();
    }
  }
  const isConference = kind === 'conference';
  return N.createElement('section', {
    className: isConference ? 'hc27-hero' : 'hc27-mission',
    id: isConference ? 'conference-2027' : 'mission-2030',
    'aria-label': isConference ? 'Telangana Stakeholder Conference 2027' : 'Mission 2030'
  },
    N.createElement('div', {onClick: openDetails, dangerouslySetInnerHTML: {__html: markup}}),
    N.createElement('dialog', {
      className: 'hc27-dialog', ref: dialog,
      'aria-labelledby': 'hc27-' + kind + '-title',
      onClick: event => {if (event.target === dialog.current) {
        const box = dialog.current.getBoundingClientRect();
        if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) dialog.current.close();
      }},
      onClose: () => opener.current?.focus()
    },
      N.createElement('form', {method: 'dialog', className: 'hc27-close-form'},
        N.createElement('button', {type: 'submit', className: 'hc27-close', 'aria-label': 'Close details', autoFocus: true}, '×')),
      N.createElement('div', {dangerouslySetInnerHTML: {__html: details}})
    )
  );
}
function HC27ConferenceHero(){return N.createElement(HC27Section,{kind:'conference',markup:HC27_HERO,details:HC27_CONFERENCE_DETAILS});}
function HC27Mission(){return N.createElement(HC27Section,{kind:'mission',markup:HC27_MISSION,details:HC27_MISSION_DETAILS});}
