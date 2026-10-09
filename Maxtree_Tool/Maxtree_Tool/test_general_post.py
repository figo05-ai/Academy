post=dict(result='RUNNING',steps=[])
write('post_results.json',post)
post_warnings=[]
def collect_warning():
    d=QtWidgets.QApplication.activeModalWidget()
    if isinstance(d,QtWidgets.QMessageBox):post_warnings.append(d.text());d.accept()
post_modal=QtCore.QTimer();post_modal.setInterval(400);post_modal.timeout.connect(collect_warning);post_modal.start()
def post_checks():
    try:
        # New source and category searches are checked on the final reloaded build.
        manager.refresh(ts['libraries']['bulk']);manager.category_filter.setCurrentText('Architecture');manager.search.setText('Part_A');assert manager.list.count()==1
        post['steps'].append(dict(action='variant_and_category_search',result='PASS'))
        manager.category_filter.setCurrentIndex(0);manager.search.setText('General validation bulk');assert len(manager.filtered)==4
        post['steps'].append(dict(action='library_name_search',result='PASS'))
        # Material-less geometry is a valid general block.
        bare=dict(path=fixtures['BareBlock']['path'],label='Bare block',variants=[],renderer_policy='source',maps_folder=str(FIX/'Maps'))
        before=ids();imp=maxtree_library.Importer();node,paths,_=imp.insert(bare)
        assert node.material is None and len(ids()-before)==1
        rt.delete(node);post['steps'].append(dict(action='materialless_block',result='PASS'))
        # Verify real existing plant support through the visible main browser.
        manager.hide();w.show();w.raise_();w.collection.setCurrentIndex(0);w.species.setCurrentIndex(0);w.search.setText('MT_PM_V67_Carica_papaya_01_03')
        assert w.grid.count()==1
        entry=w.grid.currentItem().data(QtCore.Qt.UserRole);source=Path(entry['path']);source_before=fp(source)
        before=ids();paths_before=[str(rt.mapPaths.get(i)) for i in range(1,int(rt.mapPaths.count())+1)]
        w.insert_button.click()
        created=[n for n in rt.objects if int(rt.getHandleByAnim(n)) not in before];assert len(created)==1,post_warnings
        record=w.importer.last_summary;assert record['resolved']>0 and record['missing']==record['ambiguous']==0
        assert fp(source)==source_before
        assert [str(rt.mapPaths.get(i)) for i in range(1,int(rt.mapPaths.count())+1)]==paths_before
        unchanged();post['steps'].append(dict(action='existing_maxtree_insert',result='PASS',path=str(source),maps=record,source_unchanged=True))
        rt.delete(created[0]);w.search.clear();w.move(70,50);w.raise_();w.activateWindow()
        assert len(w.entries)==base_count
        post['result']='PASS';write('post_results.json',post)
        def capture():
            pos=w.mapToGlobal(QtCore.QPoint(0,0));w.screen().grabWindow(0,pos.x(),pos.y(),w.width(),190).save(str(OUT/'general_controls.png'))
            write('final_ui.json',dict(title=w.windowTitle(),build=w.build_label.text(),visible=w.isVisible(),logo=not w.logo.pixmap().isNull(),entries=len(w.entries),modules=w.property('modulePaths'),buttons=[b.text() for b in w.findChildren(QtWidgets.QPushButton) if b.isVisible()]))
        QtCore.QTimer.singleShot(1000,capture)
    except Exception:post['result']='FAIL';post['error']=traceback.format_exc();post['warnings']=post_warnings;write('post_results.json',post)
    finally:post_modal.stop()
QtCore.QTimer.singleShot(200,post_checks)
