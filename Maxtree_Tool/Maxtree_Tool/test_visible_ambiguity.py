from collection_ui import ReviewDialog
extra=dict(result='RUNNING',steps=[])
xs=dict(phase='begin',start=time.monotonic(),cid=None)
def extra_save():write('ambiguity_results.json',extra)
extra_save()
def extra_drive():
    try:
        if extra['result']!='RUNNING':return
        if time.monotonic()-xs['start']>420:raise RuntimeError('Ambiguity test timeout '+xs['phase'])
        d=QtWidgets.QApplication.activeModalWidget()
        if isinstance(d,QtWidgets.QMessageBox):raise RuntimeError(d.text())
        if isinstance(d,asset_ui.CreateLibraryDialog):
            d.name.setText('Validation Ambiguous Previews');d.workflow.setCurrentIndex(1);d.method.setCurrentIndex(1)
            d.models.setText(modelroot);d.maps.setText(maproot);d.previews.setText(modelroot)
            xs['phase']='scan';click_accept(d);return
        if isinstance(d,asset_ui.ScanReport):
            if xs['phase']=='scan':
                assert d.summary['models']==72 and d.summary['previews']==0,d.summary
                assert all('Ambiguous preview' in ';'.join(e['issues']) for e in d.result['entries'])
                extra['steps'].append(dict(action='actual_duplicate_preview_scan',result='PASS',summary=d.summary));extra_save()
                d.cross.setChecked(True)
                row=next(i for i,e in enumerate(d.result['entries']) if e['path']==samples[0]['path'])
                for i in range(d.table.rowCount()):d.table.item(i,0).setCheckState(QtCore.Qt.Checked if i==row else QtCore.Qt.Unchecked)
                d.table.setCurrentCell(row,1);xs['phase']='review'
                next(b for b in d.findChildren(QtWidgets.QPushButton) if b.text().startswith('Review selected')).click();return
            if xs['phase']=='reviewed':
                chosen=d.selected();assert len(chosen)==1 and chosen[0]['image']==samples[0]['image']
                xs['phase']='added';click_accept(d);return
        if isinstance(d,ReviewDialog) and xs['phase']=='review':
            d.preview.setCurrentIndex(d.preview.findData(samples[0]['image']))
            d.object.setCurrentIndex(d.object.findData(samples[0]['variants'][0]['object']))
            d.matlib.setCurrentIndex(d.matlib.findData(samples[0]['variants'][0]['matlib']))
            assert d.preview.currentIndex()>0 and d.matlib.currentIndex()>0
            xs['phase']='reviewed';click_accept(d);return
        if d and d.windowTitle()=='Review material mapping':
            assert xs['phase']=='inserting'
            combos=d.findChildren(QtWidgets.QComboBox);assert combos
            idx=next(i for i in range(combos[0].count()) if combos[0].itemText(i).startswith(samples[0]['variants'][0]['material']+' [library slot'))
            combos[0].setCurrentIndex(idx)
            screenshot_top(d,'material_mapping_controls.png')
            extra['steps'].append(dict(action='visible_material_mapping_review',result='PASS',selected=combos[0].currentText()));extra_save()
            click_accept(d);return
        if xs['phase']=='begin':
            xs['phase']='wizard';manager.actions['Create Library'].click();return
        if xs['phase']=='added' and not d and manager.job is None:
            assert len(manager.filtered)==1 and manager.selected()['image']==samples[0]['image']
            xs['cid']=manager.cid();sentinel.material.name=samples[0]['variants'][0]['material'];h=int(rt.getHandleByAnim(sentinel.material));before=list(rt.objects)
            xs['phase']='inserting';manager.insert.click()
            assert int(rt.getHandleByAnim(sentinel.material))==h
            new=[n for n in rt.objects if n not in before];assert len(new)==1
            assignments=manager.last_insert['material_assignments'];assert assignments and assignments[0]['exact']==False
            assert str(new[0].material.name)==samples[0]['variants'][0]['material']
            assert all(Path(x).is_file() for x in manager.last_insert['maps'])
            extra['steps'].append(dict(action='explicit_mapping_insert',result='PASS',assignments=assignments,map_properties=len(manager.last_insert['maps']),unrelated_material_preserved=True))
            rt.delete(new[0]);asset_store.update(xs['cid'],removed=True);manager.refresh()
            assert all(fingerprint(Path(p))==v for p,v in baseline.items())
            extra['result']='PASS';extra['protected_files_unchanged']=True;extra_save();extra_timer.stop();extra_modal.stop()
    except Exception:
        extra['result']='FAIL';extra['error']=traceback.format_exc();extra_save();extra_timer.stop();extra_modal.stop()
extra_timer=QtCore.QTimer();extra_timer.setInterval(700);extra_timer.timeout.connect(extra_drive)
extra_modal=QtCore.QTimer();extra_modal.setInterval(600);extra_modal.timeout.connect(lambda:extra_drive() if QtWidgets.QApplication.activeModalWidget() is not None else None)
extra_timer.start();extra_modal.start()
builtins._asset_extra_timers=(extra_timer,extra_modal)
