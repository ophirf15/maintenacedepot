/**
 * In-app user manual content for Maintenance Depot.
 * Each section: id, title, audience, summary, howTos[], actions[], tips[], troubles[].
 * Optional anyOf / allOf permission gates hide content the viewer cannot use.
 */

export const MANUAL_SECTIONS = [
  {
    id: 'install',
    title: 'First-time install',
    icon: 'settings',
    audience: 'IT admins',
    anyOf: ['manage_it'],
    summary: 'One-time wizard when the app has not been installed yet.',
    howTos: [
      {
        title: 'Run the install wizard',
        steps: [
          'Open the site URL. If the app is not installed you are taken to Install.',
          'Welcome → Next. Enter What should the app be called?',
          'Enter the first admin Full name, Email, and Password → Next.',
          'Optionally check Add example data for demo tools and users.',
          'Tap Start set-up and wait until Done → Go to sign in.',
        ],
      },
    ],
    actions: [
      { name: 'Next / Back', where: 'Install wizard', does: 'Moves between Welcome, Admin, Check, and Done.' },
      { name: 'Add example data', where: 'Admin step', does: 'Seeds demo catalog users and sample loans for training.' },
      { name: 'Start set-up', where: 'Check step', does: 'Runs migrations and creates the first IT admin.' },
      { name: 'Go to sign in', where: 'Done', does: 'Opens the login screen.' },
    ],
    tips: [
      'Install can only run once. Afterward the route refuses another set-up.',
    ],
    troubles: [
      {
        problem: 'Start set-up fails',
        fix: 'Confirm the database is reachable and APP_KEY is set in the server environment, then retry. Contact your host if migrations error.',
      },
    ],
  },

  {
    id: 'getting-started',
    title: 'Getting started',
    icon: 'home',
    audience: 'Everyone',
    summary: 'Sign in, find your way around, and understand roles.',
    howTos: [
      {
        title: 'Sign in with a password',
        steps: [
          'Open the app and choose the Password chip on the sign-in screen.',
          'Enter the email and password your depot admin gave you.',
          'Tap Sign in. You land on Home.',
        ],
      },
      {
        title: 'Sign in with an email link',
        steps: [
          'Choose the Email link chip.',
          'Enter your work email and tap Send me a link.',
          'Open the email on this device and tap the link (or paste the token URL).',
          'If the link is expired, request a new one from Sign in.',
        ],
      },
      {
        title: 'Find things in the app',
        steps: [
          'Use the left sidebar (or phone menu) for main areas: Borrow, Depot, Admin.',
          'Use the top search box to find tools (and requests/people if you approve loans).',
          'On phones, menu opens as a bottom sheet (profile, grouped links, appearance, sign out). Swipe down to dismiss.',
          'Bottom tabs use a filled pill on the active item, like Material navigation.',
          'The top bar gains a light elevation shadow as you scroll.',
          'Open Help (book icon) in the top bar anytime for this manual.',
        ],
      },
    ],
    actions: [
          { name: 'Open menu / Close menu', where: 'Top bar (phone)', does: 'Opens a bottom sheet with profile, all nav links, appearance, and sign out. Swipe down or tap the scrim to close.' },
      { name: 'Search box', where: 'Top bar', does: 'Press Enter to open Search results for your term.' },
      { name: 'Theme (sun / moon / monitor)', where: 'Top bar', does: 'Cycles Light → Dark → Match device. Choice is saved on this browser.' },
      { name: 'Tool bag', where: 'Top bar', does: 'Opens your tool bag. Badge shows how many lines are waiting.' },
      { name: 'Notifications (bell)', where: 'Top bar', does: 'Opens your messages. Orange dot means unread.' },
      { name: 'Help (book)', where: 'Top bar', does: 'Opens this user manual.' },
      { name: 'Sign out', where: 'Sidebar bottom', does: 'Ends your session on this device and returns to Sign in.' },
    ],
    tips: [
      'Your role label under your name (for example Maintenance crew or Depot admin) controls which menus and buttons you see.',
      'If a page is missing from the menu, you may not have permission — ask an IT or depot admin.',
    ],
    troubles: [
      {
        problem: 'Sign in says Invalid credentials',
        fix: 'Check email spelling and caps lock. Ask an admin to confirm your account is active and reset the password if needed. After several failures the app briefly blocks more tries — wait a minute.',
      },
      {
        problem: 'Email link no longer works',
        fix: 'Links expire (about 30 minutes) and can only be used once. Request a new link from Sign in → Email link.',
      },
      {
        problem: 'I am sent to Install instead of the app',
        fix: 'The system has not finished first-time set-up. Only the person installing the server should complete Install. If the app is already live, contact IT.',
      },
    ],
  },

  {
    id: 'home',
    title: 'Home dashboard',
    icon: 'home',
    audience: 'Everyone',
    summary: 'Quick actions and status tiles for your role.',
    howTos: [
      {
        title: 'Use Home as a launch pad',
        steps: [
          'Open Home from the sidebar or bottom bar.',
          'Tap a quick action (Borrow a tool, Approve requests, Scan, and so on).',
          'Tap a status tile to jump to that filtered list (for example Overdue returns).',
          'Open a row under Latest requests or Active loans to see details.',
        ],
      },
    ],
    actions: [
      { name: 'Borrow a tool / Browse tools', where: 'Quick actions', does: 'Opens the tool catalog.' },
      { name: 'My requests', where: 'Quick actions / tiles', does: 'Opens your borrow requests.', anyOf: ['borrow_items'] },
      { name: 'My loans / Active loans', where: 'Quick actions / tiles', does: 'Opens loans you own or manage.' },
      { name: 'Scan pick-up or return / Scan return', where: 'Quick actions', does: 'Opens the QR / tool-number scan screen.', anyOf: ['checkout_items', 'borrow_items'] },
      { name: 'Approve requests', where: 'Admin quick actions', does: 'Opens the approval queue.', anyOf: ['approve_requests'] },
      { name: 'Damage reports', where: 'Quick actions', does: 'Opens tickets.' },
      { name: 'See all', where: 'Request / loan lists', does: 'Opens the full list for that section.' },
      { name: 'Request or loan row', where: 'Lists', does: 'Opens that item’s detail page.' },
    ],
    tips: [
      'Home tiles and shortcuts match your job — you only see actions you are allowed to use.',
    ],
    troubles: [
      {
        problem: 'Numbers on tiles look wrong',
        fix: 'Refresh the page. Filters on the destination list may also change what you see after you tap through.',
      },
    ],
  },

  {
    id: 'catalog',
    title: 'Browse tools & tool bag',
    icon: 'grid',
    audience: 'Everyone',
    summary: 'Find equipment, pack your tool bag, join a waitlist when nothing is free.',
    howTos: [
      {
        title: 'Borrow a tool (end to end)',
        anyOf: ['borrow_items'],
        steps: [
          'Go to Browse tools and open a tool group (category).',
          'Either tap Add any available unit, or Choose a specific unit and Add this unit to tool bag.',
          'Open Tool bag from the top bar.',
          'Set quantities, pick This exact unit or Any free unit, choose property, depot, and dates (or use Today only / Tomorrow / Next week).',
          'Optionally set urgency and describe the job.',
          'Tap Submit borrow request. Track it under My requests.',
        ],
      },
      {
        title: 'Join a waitlist when everything is out',
        anyOf: ['borrow_items'],
        steps: [
          'On a category with no free units, tap Join waitlist.',
          'Choose property and the dates you need the tool.',
          'Tap Join waitlist. Leave later from My requests if plans change.',
        ],
      },
    ],
    actions: [
      { name: 'Category tile', where: 'Browse tools', does: 'Opens that group’s types and units.' },
      { name: 'Tool bag (count)', where: 'Catalog pages', does: 'Jumps to the tool bag.', anyOf: ['borrow_items'] },
      { name: 'Add any available unit', where: 'Category', does: 'Adds a flexible “any free unit of this type” line to the tool bag.', anyOf: ['borrow_items'] },
      { name: 'Choose a specific unit / Hide units', where: 'Category', does: 'Expands or collapses the unit list.' },
      { name: 'Add this unit to tool bag', where: 'Unit row / peek / unit detail', does: 'Adds that exact asset to the tool bag (when loanable and available).', anyOf: ['borrow_items'] },
      { name: 'Join waitlist / Cancel', where: 'Waitlist modal', does: 'Creates or dismisses a waitlist entry.', anyOf: ['borrow_items'] },
      { name: 'Unit photo or name', where: 'Category', does: 'Opens the unit peek sheet (photos, specs, add to tool bag).' },
      { name: 'Edit in inventory', where: 'Unit detail', does: 'Opens admin item editor (inventory managers).', anyOf: ['manage_inventory'] },
      { name: 'Less / More quantity', where: 'Tool bag', does: 'Changes how many of that type/line you need.', anyOf: ['borrow_items'] },
      { name: 'Remove', where: 'Tool bag line', does: 'Deletes that line from the tool bag.', anyOf: ['borrow_items'] },
      { name: 'This exact unit / Any free unit', where: 'Tool bag line', does: 'Controls whether approval must allocate your chosen asset or any matching type.', anyOf: ['borrow_items'] },
      { name: 'Today only / Tomorrow, 1 day / Next week, 3 days', where: 'Tool bag dates', does: 'Fills need-from / return-by with common presets.', anyOf: ['borrow_items'] },
      { name: 'Urgency chips (Not urgent → Today)', where: 'Tool bag', does: 'Sets request priority for the depot.', anyOf: ['borrow_items'] },
      { name: 'Empty tool bag', where: 'Tool bag', does: 'Clears all lines on this device.', anyOf: ['borrow_items'] },
      { name: 'Submit borrow request', where: 'Tool bag', does: 'Creates and submits the request to the depot.', anyOf: ['borrow_items'] },
      { name: 'Browse tools', where: 'Empty tool bag', does: 'Returns to the catalog.' },
    ],
    tips: [
      { text: 'Tool bag contents stay in this browser until you submit or empty them.', anyOf: ['borrow_items'] },
      { text: 'Pick-up depot and property must match where you are allowed to work.', anyOf: ['borrow_items'] },
    ],
    troubles: [
      {
        problem: 'Add to tool bag is missing or disabled',
        fix: 'The unit may be out on loan, out of service, or not marked loanable. Try “any available unit” or join the waitlist.',
        anyOf: ['borrow_items'],
      },
      {
        problem: 'Submit fails validation',
        fix: 'Check that return date is after start date, property and depot are selected, and at least one line remains in the tool bag.',
        anyOf: ['borrow_items'],
      },
    ],
  },

  {
    id: 'requests',
    title: 'My requests',
    icon: 'clipboard',
    audience: 'Borrowers',
    anyOf: ['borrow_items'],
    summary: 'Track drafts, waiting approvals, modifications, and cancellations.',
    howTos: [
      {
        title: 'Send a draft request',
        steps: [
          'Open My requests → the draft → Send request to depot.',
          'Status becomes Waiting for approval.',
        ],
      },
      {
        title: 'Respond when the depot changes your request',
        steps: [
          'Open the request marked Waiting for borrower (or Needs my answer on Home).',
          'Read the proposed dates/units.',
          'Tap Accept these changes or Reject changes.',
        ],
      },
      {
        title: 'Cancel a request',
        steps: [
          'Open the request detail.',
          'Tap Cancel this request and confirm.',
          'Finished, cancelled, or rejected requests cannot be cancelled again.',
        ],
      },
    ],
    actions: [
      { name: 'New request', where: 'Request list', does: 'Starts shopping in the catalog (usual path is Browse → Cart).' },
      { name: 'Filter chips', where: 'Request list', does: 'Shows All, Not sent, Waiting for approval, Waiting for borrower, Ready to pick up, Finished, Not approved.' },
      { name: 'Leave', where: 'Waitlist row', does: 'Removes you from that waitlist.' },
      { name: 'Send request to depot', where: 'Draft detail', does: 'Submits the draft for approval.' },
      { name: 'Accept these changes / Reject changes', where: 'Modification pending', does: 'Accepts or declines depot edits.' },
      { name: 'Open loan', where: 'Approved request with loan', does: 'Jumps to the reserved or active loan.' },
      { name: 'Cancel this request', where: 'Open request', does: 'Stops the request if still cancellable.' },
    ],
    tips: [
      'Timeline on the detail page shows when it was sent, modified, approved, reserved, rejected, or cancelled.',
    ],
    troubles: [
      {
        problem: 'I cannot see My requests in the menu',
        fix: 'Your account needs the borrow permission. Ask an admin to assign the Maintenance crew (borrower) job/role.',
      },
    ],
  },

  {
    id: 'approvals',
    title: 'Approvals',
    icon: 'check-circle',
    audience: 'Approvers',
    anyOf: ['approve_requests'],
    summary: 'Allocate units, waitlist or reject lines, and finalize borrow requests.',
    howTos: [
      {
        title: 'Approve a request and reserve tools',
        steps: [
          'Open Approvals → Ready to approve.',
          'Expand a request. For each line choose Approve, Waitlist, or Reject.',
          'When Approving, pick Which unit are they getting? from the candidate list.',
          'Optionally Change dates or add a note for the depot or borrower.',
          'If you must finish without waiting on the borrower after a date change, check Approve now without waiting for the borrower.',
          'Tap Approve request. A loan is reserved when allocation succeeds.',
        ],
      },
    ],
    actions: [
      { name: 'Ready to approve / Waiting for borrower', where: 'Tabs', does: 'Switches between actionable queue and requests waiting on the borrower.' },
      { name: 'Approve / Waitlist / Reject', where: 'Per line', does: 'Sets the decision for that line.' },
      { name: 'Which unit are they getting?', where: 'Approve line', does: 'Chooses the allocated asset (prefer available candidates).' },
      { name: 'Why not?', where: 'Reject line', does: 'Records a reject reason.' },
      { name: 'Change dates or add a note / Hide extra options', where: 'Request panel', does: 'Shows pick-up/return overrides and notes.' },
      { name: 'Approve now without waiting for the borrower', where: 'Extra options', does: 'Finalizes even when dates changed (use carefully).' },
      { name: 'Full details', where: 'Queue row', does: 'Opens the full request page.' },
      { name: 'Approve request', where: 'Queue panel', does: 'Saves decisions and creates/updates the reservation.' },
    ],
    tips: [
      'The sidebar Approvals badge counts submitted requests waiting on you.',
      'If the suggested unit is unavailable, pick another candidate or waitlist the line.',
    ],
    troubles: [
      {
        problem: 'Approve request fails',
        fix: 'Every Approve line needs a unit. Rejected lines need a reason. Dates must be valid. Refresh if someone else just reserved the same tool.',
      },
    ],
  },

  {
    id: 'loans',
    title: 'Loans (pick-up, return, extensions)',
    icon: 'handshake',
    audience: 'Borrowers and checkout staff',
    summary: 'Confirm pick-up, submit returns, inspect returns, and ask for more time.',
    howTos: [
      {
        title: 'Confirm pick-up (checkout staff)',
        anyOf: ['checkout_items'],
        steps: [
          'Open Active loans → a Ready for pick-up loan (or Scan).',
          'For each tool, optionally enter the scan/QR code, set condition (Like new / Good / Okay / Bad), and fuel when it leaves.',
          'Attach suggested companions if the crew is taking them; enter estimated consumables if prompted.',
          'Only override maintenance blocks if you have a real reason — fill Why are you overriding?',
          'Tap Confirm pick-up. Status becomes Out with borrower.',
        ],
      },
      {
        title: 'Submit a self-return (borrower or staff)',
        anyOf: ['borrow_items', 'checkout_items'],
        steps: [
          'Open the loan while it is Out with borrower.',
          'For each tool set fuel, condition, hours if known, and flag damage or end-of-life if needed.',
          'Tap Submit return. Status becomes Return submitted until staff inspect.',
        ],
      },
      {
        title: 'Inspect a returned loan (checkout staff)',
        anyOf: ['checkout_items'],
        steps: [
          'Open a Return submitted loan.',
          'Confirm consumable quantities used.',
          'Mark Looks good or Problem found; set fuel/condition/hours; optionally Stop anyone using this tool.',
          'Tap Confirm return complete.',
        ],
      },
      {
        title: 'Ask for more time',
        anyOf: ['borrow_items', 'checkout_items'],
        steps: [
          'On an out loan, under Need more time?, pick a new return date and reason.',
          'Tap Ask for more time.',
          'Staff with approve permission decide Allow extra time or Decline.',
        ],
      },
      {
        title: 'Handle tools with no reservation (staff)',
        anyOf: ['checkout_items'],
        steps: [
          'Open Scan pick-up or return → section 4 (No matching loan?).',
          'For a grab-and-go tool: Walk-in pick-up — borrower, dates, then check out.',
          'For a tool coming back with no checkout record: Orphan return — borrower, condition/hours, then close.',
          'Both create a real loan so the activity log and usage stay consistent.',
        ],
      },
    ],
    actions: [
      { name: 'Scan pick-up or return', where: 'Loan list', does: 'Opens the scan workflow.', anyOf: ['checkout_items', 'borrow_items'] },
      { name: 'Filter chips', where: 'Loan list', does: 'All, Ready for pick-up, Out with borrower, Return submitted, Overdue, Closed.' },
      { name: 'See the request this came from', where: 'Loan detail', does: 'Opens the parent borrow request.' },
      { name: 'Condition chips / Fuel slider', where: 'Pick-up & return forms', does: 'Records condition and fuel percentage.', anyOf: ['checkout_items', 'borrow_items'] },
      { name: 'Companion chips / Scan code', where: 'Pick-up', does: 'Attaches companion tools suggested for the primary items.', anyOf: ['checkout_items'] },
      { name: 'Estimate consumables taken', where: 'Pick-up', does: 'Records estimated qty leaving with the loan.', anyOf: ['checkout_items'] },
      { name: 'Override and allow pick-up anyway', where: 'Pick-up', does: 'Bypasses blocking maintenance (audited).', anyOf: ['checkout_items'] },
      { name: 'Confirm pick-up', where: 'Reserved loan', does: 'Checks tools out to the borrower.', anyOf: ['checkout_items'] },
      { name: 'Something is broken / end of life flags', where: 'Self-return', does: 'Flags issues for inspection.', anyOf: ['borrow_items', 'checkout_items'] },
      { name: 'Submit return', where: 'Checked-out loan', does: 'Starts the return-pending inspection path.', anyOf: ['borrow_items', 'checkout_items'] },
      { name: 'Looks good / Problem found', where: 'Return inspection', does: 'Sets inspection outcome per tool.', anyOf: ['checkout_items'] },
      { name: 'Stop anyone using this tool', where: 'Return inspection', does: 'Takes the asset out of service.', anyOf: ['checkout_items'] },
      { name: 'Confirm return complete', where: 'Return pending', does: 'Closes the return and updates stock/status.', anyOf: ['checkout_items'] },
      { name: 'Ask for more time', where: 'Extension form', does: 'Creates a pending extension request.', anyOf: ['borrow_items', 'checkout_items'] },
      { name: 'Allow extra time / Decline', where: 'Pending extension', does: 'Approves or rejects the new due date (approvers).', anyOf: ['approve_requests'] },
    ],
    tips: [
      { text: 'Self-return can be turned off in Settings → Features.', anyOf: ['manage_settings', 'manage_it', 'checkout_items'] },
      'Overdue loans stay visible under the Overdue filter and on Home tiles.',
    ],
    troubles: [
      {
        problem: 'Confirm pick-up blocked for maintenance',
        fix: 'Complete the service plan or use a documented override. Do not override casually — it is logged.',
        anyOf: ['checkout_items'],
      },
      {
        problem: 'I cannot open someone else’s loan',
        fix: 'Borrowers only see their own loans. Staff with checkout permission can open any loan.',
      },
      {
        problem: 'Extension is declined or fails',
        fix: 'Another reservation may already exist for the same tool. Return on time or ask the depot to rebook.',
      },
    ],
  },

  {
    id: 'scan',
    title: 'Scan pick-up or return',
    icon: 'scan',
    audience: 'Checkout staff and borrowers',
    anyOf: ['checkout_items', 'borrow_items'],
    summary: 'Camera or typed tool numbers, online confirm, offline queue.',
    howTos: [
      {
        title: 'Scan tools for pick-up or return',
        steps: [
          'Open Scan pick-up or return.',
          'Choose Pick-up or Return.',
          'Tap Use the camera and point at the QR sticker, or type the 6-digit tool number.',
          'Optionally enter Loan number if you know it.',
          'Tap Add to the list for each tool, then Confirm scans.',
          'If you have No signal, scans stay on the phone until you have signal and confirm again.',
        ],
      },
      {
        title: 'Walk-in pick-up (no prior request)',
        anyOf: ['checkout_items'],
        steps: [
          'If Confirm scans says no active loan, tap Start walk-in checkout on the failed row — or open section 4.',
          'Enter the tool number, search and select the borrower, choose property and depot.',
          'Set Return by and optional notes (why there was no request).',
          'Tap Create walk-in loan & check out. Open the loan link to confirm details.',
        ],
      },
      {
        title: 'Orphan return (tool never checked out)',
        anyOf: ['checkout_items'],
        steps: [
          'On a failed Return scan with no loan, tap Record orphan return — or use section 4 → Orphan return.',
          'Select who had the tool, property, depot, inbound condition, fuel, and hours used.',
          'Flag damage if needed, add notes, then Create loan & record return.',
          'The system creates a short loan, checks it out, and closes the return so usage is audited.',
        ],
      },
    ],
    actions: [
      { name: 'Use the camera / Stop the camera', where: 'Scan page', does: 'Starts or stops the device camera QR reader.' },
      { name: 'Tool number', where: 'Scan form', does: 'Manual entry when the camera cannot read the code.' },
      { name: 'Pick-up / Return', where: 'Scan form', does: 'Chooses the action applied when syncing.' },
      { name: 'Loan number', where: 'Scan form', does: 'Optional hint; the server still matches the scanned tool to an active loan.' },
      { name: 'Add to the list', where: 'Scan form', does: 'Queues an event locally.' },
      { name: 'Remove', where: 'Queued row', does: 'Deletes a queued scan before confirm.' },
      { name: 'Confirm scans', where: 'Scan page', does: 'Sends the queue to the depot (requires checkout permission for sync).', anyOf: ['checkout_items'] },
      { name: 'You have signal / No signal', where: 'Badge', does: 'Shows whether the browser is online.' },
      { name: 'Start walk-in checkout… / Record orphan return…', where: 'Failed scan row', does: 'Opens the exception form for that tool number.', anyOf: ['checkout_items'] },
      { name: 'Walk-in pick-up / Orphan return', where: 'Section 4', does: 'Switches exception mode.', anyOf: ['checkout_items'] },
      { name: 'Borrower search', where: 'Exception form', does: 'Finds active people to attach to the ad-hoc loan.', anyOf: ['checkout_items'] },
      { name: 'Create walk-in loan & check out', where: 'Exception form', does: 'Creates a loan and checks the tool out immediately.', anyOf: ['checkout_items'] },
      { name: 'Create loan & record return', where: 'Exception form', does: 'Creates a short loan, checks out, then closes with return inspection.', anyOf: ['checkout_items'] },
      { name: 'Open loan #…', where: 'After exception', does: 'Jumps to the loan detail page.', anyOf: ['checkout_items'] },
    ],
    tips: [
      { text: 'Offline scanning must be enabled under Settings → Features.', anyOf: ['manage_settings', 'manage_it', 'checkout_items'] },
      'Successful rows show Saved to the depot; failures show Did not send with an error.',
      { text: 'Walk-in and orphan return need signal — they are not queued offline.', anyOf: ['checkout_items'] },
    ],
    troubles: [
      {
        problem: 'Camera will not start',
        fix: 'Allow camera permission in the browser. Prefer HTTPS or localhost. Use typed tool number as a fallback.',
      },
      {
        problem: 'Confirm scans is forbidden',
        fix: 'Syncing checkouts requires checkout permission. Ask a depot admin to sync if you only have borrow access.',
        anyOf: ['borrow_items'],
      },
      {
        problem: 'No active loan for this QR',
        fix: 'Wrong action (Pick-up vs Return), or the tool was never reserved. Staff: use Walk-in pick-up or Orphan return in section 4.',
      },
      {
        problem: 'Walk-in says tool already on a loan',
        fix: 'Open that loan reference and use normal check-out or return. Do not create a second loan for the same tool.',
        anyOf: ['checkout_items'],
      },
    ],
  },

  {
    id: 'tickets',
    title: 'Damage reports (tickets)',
    icon: 'ticket',
    audience: 'Everyone',
    summary: 'Report problems, attach photos, take tools out of service, and mark fixed.',
    howTos: [
      {
        title: 'Report damage or a defect',
        steps: [
          'Open Damage reports → Report damage.',
          'Fill What is wrong?, details, type, severity, and optional tool / loan numbers.',
          'Staff may check Not safe to use — take it out of service now. Crew reports escalate severity for staff review instead of auto OOS.',
          'Tap Submit report. Add photos from the ticket detail.',
        ],
      },
      {
        title: 'Resolve a ticket (ticket managers)',
        anyOf: ['manage_tickets'],
        steps: [
          'Open the ticket → Start fixing when work begins.',
          'Optionally Take out of service.',
          'When done, fill resolution notes/cost and whether the tool can be used again → Mark fixed.',
        ],
      },
    ],
    actions: [
      { name: 'Report damage / Cancel', where: 'Ticket list', does: 'Opens or closes the create form.' },
      { name: 'Submit report', where: 'Create form', does: 'Creates an open ticket.' },
      { name: 'Filter chips', where: 'Ticket list', does: 'All, Open, Being fixed, Fixed, Closed.' },
      { name: 'Add photo', where: 'Ticket detail', does: 'Uploads a JPEG/PNG/WebP image (max ~10 MB).' },
      { name: 'Start fixing', where: 'Open ticket (managers)', does: 'Moves status to Being fixed.', anyOf: ['manage_tickets'] },
      { name: 'Take out of service', where: 'Managers', does: 'Stops the tool from being borrowed.', anyOf: ['manage_tickets', 'manage_inventory'] },
      { name: 'Mark fixed', where: 'Resolve form', does: 'Closes the ticket; can restore the tool to service.', anyOf: ['manage_tickets'] },
    ],
    tips: [
      'You only see tickets you reported unless you manage tickets — then you see the full queue.',
    ],
    troubles: [
      {
        problem: 'Photo upload fails',
        fix: 'Use a common image type under 10 MB. Stay signed in. Retry on a stable connection.',
      },
      {
        problem: 'I cannot take a tool out of service',
        fix: 'Only users with ticket or inventory management permission can force out-of-service from a report.',
        anyOf: ['borrow_items'],
      },
    ],
  },

  {
    id: 'inventory',
    title: 'Equipment list & item detail',
    icon: 'boxes',
    audience: 'Inventory managers',
    anyOf: ['manage_inventory', 'manage_catalog'],
    summary: 'Add tools, print labels, link companions, manage manuals and stock meta.',
    howTos: [
      {
        title: 'Add a tool',
        steps: [
          'Open Equipment list → Add a tool.',
          'Fill name, kind (tool type), status, depot, asset tag, and optional QR/serial/costs/lifespan.',
          'Mark People can borrow it and/or It gets used up for consumables.',
          'Tap Save tool, then open the item to upload a photo and print a sticker.',
        ],
      },
      {
        title: 'Print or download labels',
        steps: [
          'On Equipment list, choose Label size, select rows (or Select all).',
          'Use PDF selected / ZIP selected, or PDF all / ZIP all.',
          'Or open an item → Download sticker / Print to NiimBot after Make sticker if needed.',
        ],
      },
      {
        title: 'Link companions or consumables',
        steps: [
          'Open the primary item → Links.',
          'Search for the related item, choose Companion or Consumable, optionally Suggest at pick-up.',
          'Tap Link them. Unlink later with Unlink.',
        ],
      },
    ],
    actions: [
      { name: 'Label size', where: 'Inventory bar', does: 'Sets sticker format for exports/prints.' },
      { name: 'PDF all / ZIP all / PDF selected / ZIP selected', where: 'Inventory bar', does: 'Downloads label packages.' },
      { name: 'Print to NiimBot', where: 'Inventory / item', does: 'Sends the label to a paired NiimBot printer (browser Bluetooth).' },
      { name: 'Add a tool / Close', where: 'Inventory', does: 'Shows or hides the create form.' },
      { name: 'Save tool', where: 'Create form', does: 'Creates the inventory record.' },
      { name: 'Search / Select all', where: 'List', does: 'Filters and bulk-selects for labels.' },
      { name: 'Download / NiimBot', where: 'Row actions', does: 'Label for one asset.' },
      { name: 'Upload photo / Replace photo', where: 'Item detail', does: 'Sets the catalog image.' },
      { name: 'Save specs', where: 'Item detail', does: 'Stores type-specific specification fields.' },
      { name: 'Make sticker / Download sticker', where: 'Item detail', does: 'Generates or downloads the QR label art.' },
      { name: 'Upload manual / Open the manual', where: 'Item detail', does: 'Attaches or opens the PDF/manual file.' },
      { name: 'Link them / Unlink', where: 'Item links', does: 'Creates or removes companion/consumable relationships.' },
      { name: 'Save supplier & reorder / Restock', where: 'Consumable item', does: 'Updates reorder fields or adds stock.' },
    ],
    tips: [
      'QR sticker token and 6-digit numeric code identify the tool at scan time — do not reuse tokens across assets.',
    ],
    troubles: [
      {
        problem: 'NiimBot print does nothing',
        fix: 'Use a supported browser with Bluetooth, power on the printer, and allow device access. Try Download sticker as a fallback.',
      },
      {
        problem: 'Duplicate asset tag or QR errors',
        fix: 'Each asset tag and QR token must be unique. Clear or change the conflicting value.',
      },
    ],
  },

  {
    id: 'consumables',
    title: 'Consumables stock',
    icon: 'package',
    audience: 'Inventory managers',
    anyOf: ['manage_inventory'],
    summary: 'Monitor low stock, restock, and set on-hand quantities.',
    howTos: [
      {
        title: 'Restock a consumable',
        steps: [
          'Open Consumables. Optionally enable Low stock only.',
          'Tap Restock or Set qty on a row.',
          'Enter Add quantity or Set on-hand, add notes, then Save.',
        ],
      },
    ],
    actions: [
      { name: 'Search / Low stock only', where: 'Consumables', does: 'Filters the list.' },
      { name: 'Restock', where: 'Row', does: 'Opens add-quantity movement.' },
      { name: 'Set qty', where: 'Row', does: 'Opens absolute on-hand adjustment.' },
      { name: 'Save / Cancel', where: 'Stock dialog', does: 'Commits or discards the movement (audited).' },
    ],
    tips: [
      'Checkout can estimate consumables leaving with a loan; return inspection confirms qty used and adjusts stock.',
    ],
    troubles: [
      {
        problem: 'Item missing from Consumables',
        fix: 'Edit the item and enable It gets used up (is consumable), then save.',
      },
    ],
  },

  {
    id: 'catalog-admin',
    title: 'Tool groups and types',
    icon: 'tools',
    audience: 'Catalog managers',
    anyOf: ['manage_catalog'],
    summary: 'Organize the browse tree, icons, spec fields, and “often needs” links.',
    howTos: [
      {
        title: 'Create a tool group and type',
        steps: [
          'Open Tool groups and types → Tool groups → enter name, tile colour, picture → Add group.',
          'Switch to Tool types → fill name, group, tag prefix, default borrow length, picture.',
          'Add spec fields as needed. Under Often needs, link companion/consumable types → Save “often needs”.',
          'Tap Add tool type. New inventory items can now use that type.',
        ],
      },
    ],
    actions: [
      { name: 'Tool groups / Tool types', where: 'Tabs', does: 'Switches editor mode.' },
      { name: 'Equipment list', where: 'Header link', does: 'Jumps to inventory.' },
      { name: 'Add group / Save changes / Cancel / Edit', where: 'Groups', does: 'Creates or edits categories.' },
      { name: 'Icon picker', where: 'Group/type form', does: 'Sets the tile picture.' },
      { name: 'Add spec field / Remove', where: 'Type form', does: 'Defines custom fields for units of this type.' },
      { name: 'Often needs Add / Save', where: 'Type form', does: 'Drives companion suggestions at pick-up.' },
      { name: 'Add tool type / Save changes / Cancel / Edit', where: 'Types', does: 'Creates or edits tool types.' },
    ],
    tips: [
      'Tag prefix helps generate readable asset tags when adding tools.',
    ],
    troubles: [
      {
        problem: 'Cannot delete a group still in use',
        fix: 'Move or reassign tool types first. The UI may only allow edit when children exist.',
      },
    ],
  },

  {
    id: 'maintenance',
    title: 'Servicing (maintenance)',
    icon: 'wrench',
    audience: 'Maintenance managers',
    anyOf: ['manage_maintenance'],
    summary: 'Service kinds, plans, jobs, and logging completed work.',
    howTos: [
      {
        title: 'Put a service plan on a tool',
        steps: [
          'Open Servicing → Service plans.',
          'Name the plan, choose kind, pick a tool or whole tool group, set the trigger (calendar/hours/etc.) and interval.',
          'Enable Block pick-up until serviced if the tool must not leave while overdue.',
          'Tap Add the plan. Overdue plans appear on tiles and can block checkout.',
        ],
      },
      {
        title: 'Log completed work',
        steps: [
          'Open Jobs (or create one with What needs doing?).',
          'On the job, enter Hours worked, Parts cost, and notes → Log the work.',
          'Next due dates roll forward for matching triggers.',
        ],
      },
    ],
    actions: [
      { name: 'Kinds of service / Service plans / Jobs', where: 'Tabs', does: 'Switches maintenance areas.' },
      { name: 'Add it', where: 'Kinds form', does: 'Creates a maintenance type (Planned / Repair / Check).' },
      { name: 'Nobody can use the tool during this service', where: 'Kind form', does: 'Marks the type as blocking availability.' },
      { name: 'Add the plan', where: 'Plans form', does: 'Attaches a recurring or trigger-based plan.' },
      { name: 'Block pick-up until serviced', where: 'Plan form', does: 'Makes overdue plans block Confirm pick-up.' },
      { name: 'Add the job', where: 'Jobs form', does: 'Creates a work order.' },
      { name: 'Log the work', where: 'Job complete', does: 'Completes the work order and advances plan due dates.' },
    ],
    tips: [
      'Due for service vs Overdue for service tiles summarize workload at a glance.',
    ],
    troubles: [
      {
        problem: 'Checkout still blocked after service',
        fix: 'Confirm the work order was logged against the correct plan/tool. Refresh the loan page.',
      },
    ],
  },

  {
    id: 'capex-audit',
    title: 'Budget plan & activity log',
    icon: 'chart',
    audience: 'Admins',
    anyOf: ['view_capex', 'view_audit'],
    summary: 'Replacement forecasting exports and immutable-style audit browsing.',
    howTos: [
      {
        title: 'Export the budget plan',
        anyOf: ['view_capex'],
        steps: [
          'Open Budget plan.',
          'Review Tools in the plan and yearly Replace in {year} groups.',
          'Tap Excel or PDF to download.',
        ],
      },
      {
        title: 'Review activity',
        anyOf: ['view_audit'],
        steps: [
          'Open Activity log.',
          'Filter by event, subject, and date range.',
          'Tap Download for CSV, or use Back / Next to page.',
        ],
      },
    ],
    actions: [
      { name: 'Excel / PDF', where: 'Budget plan', does: 'Downloads forecast exports.', anyOf: ['view_capex'] },
      { name: 'Download', where: 'Activity log', does: 'Exports filtered audit events as CSV.', anyOf: ['view_audit'] },
      { name: 'Filters + Back / Next', where: 'Activity log', does: 'Narrows and pages the event list.', anyOf: ['view_audit'] },
    ],
    tips: [
      { text: 'Audit events cover logins, checkouts, returns, settings changes, stock moves, and more. There is no delete button in the app.', anyOf: ['view_audit'] },
    ],
    troubles: [
      {
        problem: 'Budget plan is empty',
        fix: 'Ensure tools have purchase/replacement cost and lifespan (or end-of-life flags). Enable Budget plan screen under Features if the nav link is missing.',
        anyOf: ['view_capex'],
      },
    ],
  },

  {
    id: 'settings',
    title: 'Settings (IT admin)',
    icon: 'settings',
    audience: 'IT / settings managers',
    anyOf: ['manage_it', 'manage_settings'],
    summary: 'Branding, email, SMS, SAML, features, labels, people, sites, backups, updates.',
    howTos: [
      {
        title: 'Brand the app',
        anyOf: ['manage_settings', 'manage_it'],
        steps: [
          'Open Settings → Branding.',
          'Set app name, primary colour, support email, label ownership line.',
          'Upload logo and favicon → Save changes.',
        ],
      },
      {
        title: 'Add a person and job',
        anyOf: ['manage_users', 'manage_roles', 'manage_it'],
        steps: [
          'Jobs tab: Add job and toggle permissions.',
          'People tab: Full name, email, first password, assign jobs → Add person.',
          'Use Sites to add properties/depots people can be attached to.',
        ],
      },
      {
        title: 'Tune label layout',
        anyOf: ['manage_settings', 'manage_it'],
        steps: [
          'Labels tab: pick size, fields, QR position, fonts, name size, logo, field order.',
          'Save layout, then Preview before printing a batch from Equipment list.',
        ],
      },
      {
        title: 'Back up the system',
        anyOf: ['manage_it'],
        steps: [
          'Backups → Make a backup now.',
          'Download the file and store it securely (it can contain sensitive operational data).',
        ],
      },
    ],
    actions: [
      { name: 'Tab chips (Branding … Updates)', where: 'Settings', does: 'Switches configuration panels.' },
      { name: 'Save changes', where: 'Most tabs', does: 'Persists that group (sensitive fields encrypted server-side).' },
      { name: 'Logo / Favicon upload', where: 'Branding', does: 'Stores public branding assets.' },
      { name: 'Features toggles', where: 'Features', does: 'Waiting list, Self return, Offline scanning, Budget plan screen.' },
      { name: 'Save layout / Preview', where: 'Labels', does: 'Stores label designer settings; preview renders a sample sticker.' },
      { name: 'Add person / Add job / Add site / Add status / Add field', where: 'Admin tabs', does: 'Creates directory and catalog metadata.', anyOf: ['manage_users', 'manage_roles', 'manage_properties', 'manage_catalog', 'manage_it'] },
      { name: 'Permission chips', where: 'Jobs', does: 'Toggles what that job may do.', anyOf: ['manage_roles', 'manage_it'] },
      { name: 'Alert channel matrix Save choices', where: 'Alerts', does: 'In app / Email / Text per notification type.' },
      { name: 'Make a backup now / Download', where: 'Backups', does: 'Creates or downloads backup archives (IT only).', anyOf: ['manage_it'] },
      { name: 'Check for updates', where: 'Updates', does: 'Queries GitHub Releases for a newer package.', anyOf: ['manage_updates', 'manage_it'] },
      { name: 'Install v…', where: 'Updates', does: 'Downloads the update zip, overlays app files, runs migrations, then reloads.', anyOf: ['manage_updates', 'manage_it'] },
    ],
    tips: [
      'SMTP password, Twilio auth token, SAML certificate, and GitHub token are stored encrypted and are not returned when you reload the form.',
      'SAML sign-in stays disabled until a signature-verified SAML integration is configured — do not enable SSO expecting raw JSON login.',
    ],
    troubles: [
      {
        problem: 'Magic links or email alerts never arrive',
        fix: 'Verify Email (SMTP) host, port, encryption, from address, and password. Check spam. On local installs mail may be logged only.',
      },
      {
        problem: 'Texts never send',
        fix: 'Enable Send text messages and verify Twilio Account SID, Auth token, and From number.',
      },
      {
        problem: 'Person cannot sign in',
        fix: 'Confirm Can sign in (active), correct job/permissions, and password. Blocked users must be re-enabled.',
      },
    ],
  },

  {
    id: 'notifications-search',
    title: 'Notifications & search',
    icon: 'bell',
    audience: 'Everyone',
    summary: 'Messages inbox and global search from the top bar.',
    howTos: [
      {
        title: 'Clear your inbox',
        steps: [
          'Open Notifications (bell).',
          'Tap a message to mark it read (and follow its link when present).',
          'Or tap Mark all read.',
        ],
      },
      {
        title: 'Search the depot',
        steps: [
          'Type in the top search box and press Enter.',
          'Open a tool result (catalog or inventory depending on your permissions) or a request result.',
        ],
      },
    ],
    actions: [
      { name: 'Mark all read', where: 'Notifications', does: 'Clears unread state for every message.' },
      { name: 'Notification row', where: 'Notifications', does: 'Marks read and navigates when an action URL exists.' },
      { name: 'Result links', where: 'Search', does: 'Open matching tools or requests.' },
    ],
    tips: [
      'Approvers search tools, requests, and people; crew search focuses on tools.',
    ],
    troubles: [
      {
        problem: 'No search results',
        fix: 'Try a shorter term, asset tag, or email. You only see requests you are allowed to access.',
      },
    ],
  },

  {
    id: 'status-glossary',
    title: 'Status glossary',
    icon: 'info',
    audience: 'Everyone',
    summary: 'Plain-language meanings for request, loan, item, and ticket statuses.',
    howTos: [
      {
        title: 'Read statuses in the UI',
        steps: [
          'Badges use the labels below across lists and detail pages.',
          'When stuck, match the badge to the workflow step in Loans or Requests.',
        ],
      },
    ],
    actions: [
      { name: 'Request: Not sent yet', where: 'Requests', does: 'Draft — not visible to approvers until sent.' },
      { name: 'Request: Waiting for approval', where: 'Requests', does: 'In the approver queue.' },
      { name: 'Request: Waiting for borrower', where: 'Requests', does: 'Depot proposed changes; borrower must accept or reject.' },
      { name: 'Request: Approved / Rejected / Cancelled / Finished', where: 'Requests', does: 'Terminal or success states for the request lifecycle.' },
      { name: 'Loan: Ready for pick-up', where: 'Loans', does: 'Reserved — waiting for Confirm pick-up / scan.' },
      { name: 'Loan: Out with borrower', where: 'Loans', does: 'Checked out; due date applies.' },
      { name: 'Loan: Return submitted', where: 'Loans', does: 'Self-return received; staff must inspect.' },
      { name: 'Loan: Overdue — return now', where: 'Loans', does: 'Past due while still out.' },
      { name: 'Loan: Returned / Closed', where: 'Loans', does: 'Back in the depot / fully closed.' },
      { name: 'Item: Available / Out on loan / Out of service / In repair', where: 'Catalog & inventory', does: 'Whether the unit can be borrowed.' },
      { name: 'Ticket: Open / Being fixed / Fixed / Closed', where: 'Damage reports', does: 'Work progress on a problem report.' },
    ],
    tips: [
      'Line statuses (Waiting, Unit assigned, Picked up, On waitlist) appear inside request details per tool line.',
    ],
    troubles: [],
  },

  {
    id: 'troubleshooting',
    title: 'General troubleshooting',
    icon: 'alert',
    audience: 'Everyone',
    summary: 'Common app-wide problems and who can fix them.',
    howTos: [
      {
        title: 'Before you call IT',
        steps: [
          'Confirm you are on the correct site URL and signed in as yourself.',
          'Hard-refresh the browser (Ctrl+F5) or try a private window.',
          'Check the top-bar signal cues on Scan and any red toast messages.',
          'Note the exact button you pressed and any error text for your admin.',
        ],
      },
    ],
    actions: [
      { name: 'Try again', where: 'Error panels', does: 'Reloads the failed request or list.' },
      { name: 'Toast messages', where: 'Any page', does: 'Short success/error notices after actions.' },
    ],
    tips: [
      'Permission errors usually mean the page opened by URL but your job cannot perform that action.',
      'Rate limiting on sign-in is intentional — wait about a minute after many failed attempts.',
    ],
    troubles: [
      {
        problem: 'Page is blank or stuck loading',
        fix: 'Check network, refresh, and sign out/in. If only one page fails, report that URL to IT with the time it happened.',
      },
      {
        problem: 'Buttons I expect are missing',
        fix: 'Menus and actions are role-based. Compare with a colleague who has the right job, or ask IT to adjust Jobs permissions.',
      },
      {
        problem: 'I see someone else’s data',
        fix: 'That should not happen for crew accounts — sign out immediately and notify IT. Staff roles intentionally see depot-wide queues.',
      },
      {
        problem: 'App looks wrong after an update',
        fix: 'Hard-refresh to load new assets. Clear site data only if IT asks (you will need to sign in again; local cart/offline queue may clear).',
      },
    ],
  },
];
